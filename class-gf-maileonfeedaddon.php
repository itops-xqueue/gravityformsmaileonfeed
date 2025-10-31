<?php

if (!defined('ABSPATH')) {
    exit;
}
GFForms::include_feed_addon_framework();

use App\includes\MaileonService;
use de\xqueue\maileon\api\client\account\AccountService;
use de\xqueue\maileon\api\client\contacts\Contact;
use de\xqueue\maileon\api\client\contacts\Permission;
use de\xqueue\maileon\api\client\contacts\StandardContactField;
use de\xqueue\maileon\api\client\transactions\Transaction;


if (file_exists(plugin_dir_path(__FILE__) . '/vendor/autoload.php')) {
    require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';
}

require_once(plugin_dir_path(__FILE__) . '/includes/MaileonService.php');


class GFMaileonFeedAddOn extends GFFeedAddOn
{

    protected $_version = GF_MAILEON_FEED_ADDON_VERSION;
    protected $_min_gravityforms_version = '1.9.16';
    protected $_slug = 'gravityformsmaileonfeed';
    protected $_path = 'gravityformsmaileonfeed/gravityformsmaileonfeed.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Gravity Forms Maileon Add-On';
    protected $_short_title = 'Maileon';

    protected $_enable_rg_autoupgrade = true;

    private static ?GFMaileonFeedAddOn $_instance = null;

    /**
     * Get an instance of this class.
     *
     * @return GFMaileonFeedAddOn|null
     */
    public static function get_instance(): ?GFMaileonFeedAddOn
    {
        if (self::$_instance == null) {
            self::$_instance = new GFMaileonFeedAddOn();
        }

        return self::$_instance;
    }


    /**
     * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
     */
    public function init(): void
    {

        parent::init();
        wp_enqueue_script('wp-util');


        $this->add_delayed_payment_support(
            array(
                'option_label' => esc_html__('Subscribe contact to service x only when payment is received.', 'gravityformsmaileonfeed'),
            )
        );

    }


    public function get_menu_icon(): string
    {

        $iconString = '<svg width="24" height="24" viewBox="0 0 141 131" fill="none" xmlns="http://www.w3.org/2000/svg">
<path fill-rule="evenodd" clip-rule="evenodd" d="M104.068 130.407C104.068 122.897 97.9798 116.808 90.4693 116.808H72.0032C36.5026 116.808 19.7796 101.635 19.7796 66.8499C19.7796 35.5006 36.875 22.425 69.4266 22.425C101.978 22.425 119.759 23.0745 119.759 54.9965C119.759 63.5361 118.649 62.5004 115.166 59.2483C111.575 55.8954 105.461 50.1868 95.4372 50.1868C87.106 50.1868 83.6082 52.5302 81.8765 53.6903C81.2316 54.1224 80.8317 54.3903 80.5184 54.312C80.0067 54.1841 79.7258 53.1333 78.9862 50.3668C78.5122 48.5938 77.8497 46.116 76.8174 42.7248C71.2641 45.4943 66.3318 48.1146 61.9724 50.5928C59.6174 48.7898 56.6726 47.7185 53.4776 47.7185C45.7531 47.7185 39.4911 53.9804 39.4911 61.7049C39.4911 63.1315 39.7047 64.5082 40.1016 65.8047C21.2898 83.8108 46.1221 89.8886 70.1813 90.5008C71.1547 90.2151 70.5682 91.7332 69.7276 93.9088C68.2324 97.779 65.9332 103.73 70.1813 105.31C73.2395 106.447 75.96 103.217 79.172 99.4023C82.9299 94.9396 87.3605 89.6781 93.7917 89.6781C100.201 89.6781 99.2476 93.4782 98.2696 97.3778C97.4274 100.736 96.5667 104.168 100.374 105.31C105.372 106.809 109.13 101.629 112.248 97.3309C114.263 94.5543 116.01 92.1463 117.651 92.1463C135.305 92.1463 140.571 84.9183 140.571 56.7182C140.571 14.6328 114.232 0 69.4266 0C23.7625 0 0 27.3412 0 66.8499C0 108.363 26.7686 130.407 72.1464 130.407H104.068ZM53.4783 68.6942C57.5677 68.6942 60.8829 65.379 60.8829 61.2896C60.8829 57.2001 57.5677 53.885 53.4783 53.885C49.3888 53.885 46.0736 57.2001 46.0736 61.2896C46.0736 65.379 49.3888 68.6942 53.4783 68.6942Z" fill="#242748"/>
</svg>';

        return $this->is_gravityforms_supported('2.5-beta-4') ? $iconString : 'dashicons-admin-generic';

    }

    // # FEED PROCESSING -----------------------------------------------------------------------------------------------

    /**
     * Process the feed e.g. subscribe the user to a list.
     *
     * @param array $feed The feed object to be processed.
     * @param array $entry The entry object currently being processed.
     * @param array $form The form object currently being processed.
     *
     * @return bool|void
     */
    public function process_feed($feed, $entry, $form)
    {

        $this->sendHeartbeat('heartbeat');

//		$this->log_debug( 'feed ' . json_encode( $feed ) );
//		$this->log_debug( 'entry ' . json_encode( $entry ) );
//		$this->log_debug( 'form ' . json_encode( $form ) );
        $feedName = $feed['meta']['feedName'];
        $email = $entry[$feed['meta']['email']];
        $standardContactFieldMeta = $feed['meta']['standardContactFields'];
        $customContactFieldMeta = $feed['meta']['customContactFields'];

        $settings = $this->get_plugin_settings();

        $permission = $feed['meta']['permission'] != '' ? $feed['meta']['permission'] : rgar($settings, 'permission');


        $standardContactFields = [];
        foreach ($standardContactFieldMeta as $key => $value) {
            if (!isset($entry[$value['value']]) || $entry[$value['value']] == '') {
                switch ($value['value']) {
                    case 'form_title':
                        $standardContactFields[$value['key']] = $form['title'] ?? "";
                        break;
                    case 'id':
                        $standardContactFields[$value['key']] = $entry['id'] ?? "";
                        break;
                    case 'date_created':
                        $standardContactFields[$value['key']] = $entry['date_created'] ?? "";
                        break;
                    case 'ip':
                        $standardContactFields[$value['key']] = $entry['ip'] ?? "";
                        break;
                    case 'source_url':
                        $standardContactFields[$value['key']] = $entry['source_url'] ?? "";
                        break;
                    default:
                        break;
                }
                continue;
            }

            if (!isset($entry[$value['value']]) || $entry[$value['value']] == '') {
                continue;
            }
            $fieldKey = $value['key'];
            $standardContactFields[StandardContactField::$$fieldKey] = $entry[$value['value']];
        }

        $maileon = new MaileonService(['maileon_api_key' => $this->get_plugin_setting('maileon_api_key')]);
        $customFields = $maileon->getCustomFields();
        $customContactFields = [];
        foreach ($customContactFieldMeta as $key => $value) {

            $customKey = $value['key'] == "gf_custom" ? $value['custom_key'] : $value['key'];


            if (!isset($entry[$value['value']]) || $entry[$value['value']] == '') {
                switch ($value['value']) {
                    case 'form_title':
                        $customContactFields[$value['key']] = $form['title'] ?? "";
                        break;
                    case 'id':
                        $customContactFields[$value['key']] = $entry['id'] ?? "";
                        break;
                    case 'date_created':
                        $customContactFields[$value['key']] = $entry['date_created'] ?? "";
                        break;
                    case 'ip':
                        $customContactFields[$value['key']] = $entry['ip'] ?? "";
                        break;
                    case 'source_url':
                        $customContactFields[$value['key']] = $entry['source_url'] ?? "";
                        break;
                    default:
                        break;
                }
                continue;
            }


            if (!isset($customFields[$value['key']])) {
                $customContactFields[$customKey] = $this->convertType($entry[$value['value']], '');
                continue;
            }
            $customContactFields[$customKey] = $this->convertType($entry[$value['value']], $customFields[$value['key']][0]);
        }

        $contact = new Contact();
        $contact->email = $email;
        $contact->standard_fields = $standardContactFields;
        $contact->custom_fields = $customContactFields;
        $contact->permission = Permission::getPermission(intval($permission));
        $contact->subscribtionPage = $feedName;


        $needsDoi = false;
        $doiKey = '';


        if (intval($permission) === 4 || intval($permission) === 5) {
            if (rgar($feed['meta'], 'doi_override') == '1') {
                $needsDoi = rgar($feed['meta'], 'doi_mailing') == '1';
                $doiKey = rgar($feed['meta'], 'doi_mailing_key') ?? '';
            } else {
                // fallback to plugin default settings
                $needsDoi = $this->get_plugin_setting('doi_mailing') == '1';
                $doiKey = $this->get_plugin_setting('doi_mailing_key') ?? '';
            }
        }


        $newContact = $maileon->subscribeContactToMaileon($contact, false, $needsDoi, $doiKey);

        if (!$newContact) {
            $this->log_error($newContact);
            $this->log_error(json_encode(['contact' => $contact]));

        }
        $this->log_debug(json_encode(['contact' => $contact]));


        $this->log_debug('Contact creation - ' . json_encode($newContact));


        if ($feed['meta']['transactionType'] == '') {
            $this->log_debug('No transaction set up, closing session');

            return;
        }
        $transactionType = $feed['meta']['transactionType'];
        $transactionFieldMeta = $feed['meta']['transactionTypesMap'];


        $typeMap = $this->getTransactionFieldsByName($transactionType, $maileon);
//		$this->log_debug( json_encode( $typeMap ) );

        $transactionFields = [];
        foreach ($transactionFieldMeta as $key => $value) {

            if (!isset($entry[$value['value']]) || $entry[$value['value']] == '') {
                switch ($value['value']) {
                    case 'form_title':
                        $transactionFields[$value['key']] = $form['title'] ?? "";
                        break;
                    case 'id':
                        $transactionFields[$value['key']] = $entry['id'] ?? "";
                        break;
                    case 'date_created':
                        $transactionFields[$value['key']] = $entry['date_created'] ?? "";
                        break;
                    case 'ip':
                        $transactionFields[$value['key']] = $entry['ip'] ?? "";
                        break;
                    case 'source_url':
                        $transactionFields[$value['key']] = $entry['source_url'] ?? "";
                        break;
                    default:
                        break;
                }
                continue;
            }
            $transactionFields[$value['key']] = $this->convertType($entry[$value['value'] ?? ""], $typeMap[$value['key']]);
        }

        $transaction = new Transaction;
        $transaction->contact = $contact;
        $transaction->typeName = $transactionType;

        $transaction->content = $transactionFields;
        $sendables[] = $transaction;
        $sendType = $maileon->sendTransactions($sendables);
//		$this->log_debug( json_encode( $sendables ) );
        $this->log_debug('Transaction creation - ' . json_encode($sendType));

        if (!$sendType) {
            $this->log_error($sendType);
            $this->log_error(json_encode(['transaction' => $sendables]));

        }
        $this->log_debug(json_encode(['transaction' => $sendables]));


    }


    /**
     * Custom format the phone type field values before they are returned by $this->get_field_value().
     *
     * @param array $entry The Entry currently being processed.
     * @param string $field_id The ID of the Field currently being processed.
     * @param GF_Field_Phone $field The Field currently being processed.
     *
     * @return string
     */
    public function get_phone_field_value(array $entry, string $field_id, GF_Field_Phone $field): string
    {

        // Get the field value from the Entry Object.
        $field_value = rgar($entry, $field_id);

        // If there is a value and the field phoneFormat setting is set to standard reformat the value.
        if (!empty($field_value) && $field->phoneFormat == 'standard' && preg_match('/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches)) {
            $field_value = sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]);
        }

        return $field_value;
    }

    // # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

    /**
     * Return the scripts which should be enqueued.
     *
     * @return array
     */
    public function scripts(): array
    {
        $scripts = array(
            array(
                'handle' => 'my_script_js',
                'src' => $this->get_base_url() . '/js/formUiFunctions.js',
                'version' => $this->_version,
                'deps' => array('jquery'),
                'strings' => array(
                    'first' => esc_html__('First Choice', 'gravityformsmaileonfeed'),
                    'second' => esc_html__('Second Choice', 'gravityformsmaileonfeed'),
                    'third' => esc_html__('Third Choice', 'gravityformsmaileonfeed'),
                ),
                'enqueue' => array(
                    array(
                        'admin_page' => array('form_settings'),
                        'tab' => 'gravityformsmaileonfeed',
                    ),
                ),
            ),
        );

        return array_merge(parent::scripts(), $scripts);
    }

    /**
     * Return the stylesheets which should be enqueued.
     *
     * @return array
     */
    public function styles(): array
    {

        $styles = array(
            array(
                'handle' => 'my_styles_css',
                'src' => $this->get_base_url() . '/css/my_styles.css',
                'version' => $this->_version,
                'enqueue' => array(
                    array('field_types' => array('poll')),
                ),
            ),
        );

        return array_merge(parent::styles(), $styles);
    }

    // # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

//	/**
//	 * Creates a custom page for this add-on.
//	 */
//	public function plugin_page(): void {
//		echo 'This page appears in the Forms menu';
//	}


    /**
     * Configures the settings which should be rendered on the feed edit page in the Form Settings > Maileon Feed Add-On area.
     *
     * @return array
     */
    public function feed_settings_fields(): array
    {

        $maileon = new MaileonService(['maileon_api_key' => $this->get_plugin_setting('maileon_api_key')]);

        $permissionChoices = $this->getPermissionChoices();
        array_unshift($permissionChoices, ['label' => '']);
        $customContactFields = $maileon->getCustomFields();


        $standardContactFields = $maileon->getStandardFields();


        $transactionTypes = json_decode(json_encode($maileon->getTransactionTypes()));
        array_unshift($transactionTypes, []);


//		$transactionTypeFieldTypes = array_reduce( $transactionTypeFields, function ( $index, $field ) {
//
//		});
        $selectedTransactionType = [];

        return array(
            array(
                'title' => esc_html__('Maileon Feed Settings', 'gravityformsmaileonfeed'),
                'fields' => array(
                    array(
                        'label' => esc_html__('Feed name', 'gravityformsmaileonfeed'),
                        'type' => 'text',
                        'name' => 'feedName',
                        'class' => 'small',
                    ),
                    array(
                        'label' => esc_html__('Maileon condition', 'gravityformsmaileonfeed'),
                        'type' => 'custom_logic_type',
                        'name' => 'custom_logic',
                    ),


                ),
            ),
            array(
                'title' => esc_html__('Contact field mapping', 'gravityformsmaileonfeed'),
                'fields' => array(


                    array(
                        'name' => 'email',
                        'label' => esc_html__('Email field', 'gravityformsmaileonfeed'),
                        'type' => 'field_select',
                        'required' => true,
                        'tooltip' => '<h6>' . esc_html__('Email Field', 'gravityformsmaileonfeed') . '</h6>' . esc_html__('Select which Gravity Form field will be used as the contact email.', 'gravityformsmaileonfeed'),
                        'args' => array(
                            'input_types' => array('email')
                        )
                    ),
                    array(
                        'label' => esc_html__('Permission', 'gravityformsmaileonfeed'),
                        'type' => 'select',
                        'name' => 'permission',
                        'tooltip' => '<h6>' . esc_html__('Permission', 'gravityformsmaileonfeed') . '</h6>' . esc_html__('Specify a form specific permission level to override general settings.', 'gravityformsmaileonfeed'),
                        'choices' => $permissionChoices,
                        'default_value' => '',
                    ),
                    array(
                        'label' => esc_html__('Override Double Opt-In settings', 'gravityformsmaileonfeed'),
                        'type' => 'checkbox',
                        'name' => 'doi_override',
                        'tooltip' => esc_html__('Enable if you want to have custom DOI settings on this form', 'gravityformsmaileonfeed'),
                        'choices' => array(
                            array(
                                'label' => esc_html__('Override Double Opt-In settings', 'gravityformsmaileonfeed'),
                                'name' => 'doi_override',
                                'value' => '1',
                            ),
                        ),
                        'onchange' => 'jQuery(this).parents("form").submit();',
                        'default_value' => array(),
                    ),
                    array(
                        'label' => esc_html__('Enable Double Opt-In mailing', 'gravityformsmaileonfeed'),
                        'type' => 'checkbox',
                        'name' => 'doi_mailing',
                        'tooltip' => esc_html__('Enable if you want the double opt in mailing to be sent.', 'gravityformsmaileonfeed'),
                        'choices' => array(
                            array(
                                'label' => esc_html__('Enable Double Opt-In mailing', 'gravityformsmaileonfeed'),
                                'name' => 'doi_mailing',
                                'value' => '1',
                            ),
                        ),
                        'dependency' => array(
                            'field' => 'doi_override',
                            'values' => array('1')
                        ),
                    ),
                    array(
                        'name' => 'doi_mailing_key',
                        'tooltip' => esc_html__('The mailing key you want to be sent out when a new contact is created in Maileon. If empty, the one set as default in Maileon will be used.', 'gravityformsmaileonfeed'),
                        'label' => esc_html__('Double Opt-In mailing key', 'gravityformsmaileonfeed'),
                        'type' => 'text',
                        'class' => 'small',
                        'dependency' => array(
                            'field' => 'doi_override',
                            'values' => array('1'),
                        ),
                    ),

                    array(
                        'name' => 'standardContactFields',
                        'type' => 'generic_map',
                        'label' => esc_html__('Standard Contact Fields', 'gravityformsmaileonfeed'),
                        'key_field' => array(
                            'title' => 'Maileon field',
                            'type' => 'select',
                            'allow_custom' => false,
                            'choices' =>
                                array_reduce(array_keys($standardContactFields), function ($carry, $key) use ($standardContactFields) {
                                    $carry[] = [
                                        'label' => $key,
                                        'type' => $standardContactFields[$key]['type'] ?? $standardContactFields[$key][0],
                                        'field_type' => $standardContactFields['field_type'] ?? 'text',
                                    ];

                                    return $carry;
                                }, [])
                        ),
                        'value_field' => array(
                            'title' => 'Form field',
                            'text' => 'text',
                            'allow_custom' => false,
                        ),
                    ),
                    array(
                        'name' => 'customContactFields',
                        'type' => 'generic_map',
                        'label' => esc_html__('Custom Contact Fields', 'gravityformsmaileonfeed'),
                        'key_field' => array(
                            'title' => 'Maileon field',
                            'type' => 'select',
                            'allow_custom' => true,
                            'choices' =>
                                array_reduce(array_keys($customContactFields), function ($carry, $key) use ($customContactFields) {
                                    $carry[] = [
                                        'label' => $key,
                                        'type' => $customContactFields[$key]['type'] ?? $customContactFields[$key][0],
                                        'field_type' => $customContactFields['field_type'] ?? 'text',
                                    ];

                                    return $carry;
                                }, [])
                        ),
                        'value_field' => array(
                            'title' => 'Form field',
                            'text' => 'text',
                            'allow_custom' => false,

                        ),
                    ),
                ),
            ),
            array(
                'title' => esc_html__('Transaction mapping', 'gravityformsmaileonfeed'),
                'fields' => array(
                    array(
                        'label' => esc_html__('Transaction type', 'gravityformsmaileonfeed'),
                        'name' => 'transactionType',
                        'type' => 'transaction_type_select',
                        'required' => true,
                        'allow_custom' => false,
                        'choices' =>
                            array_reduce(array_keys($transactionTypes), function ($carry, $key) use ($transactionTypes) {
                                $carry[] = [
                                    'label' => $transactionTypes[$key]->name ?? '',
                                    'type' => 'text',
                                    'field_type' => $transactionTypes[$key] ?? 'text',
                                ];

                                return $carry;
                            }, [])

                    ),
                    array(
                        'name' => 'transactionTypesMap',
                        'type' => 'generic_map',
                        'dependency' => 'transactionType',
                        'label' => esc_html__('Transaction mapping', 'gravityformsmaileonfeed'),
                        'key_field' => array(
                            'title' => 'Maileon field',
                            'type' => 'select',
                            'allow_custom' => false,
                            'choices' =>
                                array_reduce(array_keys($transactionTypes), function ($carry, $key) use ($transactionTypes) {
                                    if (isset($transactionTypes[$key]->attributes)) {
                                        if ($this->get_setting('transactionType') == $transactionTypes[$key]->name) {
                                            foreach ($transactionTypes[$key]->attributes as $field) {
                                                if (in_array($field->type->value, [
                                                    'string',
                                                    'integer',
                                                    'double',
                                                    'boolean',
                                                    'date',
                                                    'timestamp'
                                                ])) {

                                                    $carry[] = [
                                                        'label' => $field->name ?? '',
                                                        'type' => $field->type->value ?? 'text',
                                                        // 'required' => $field->required,
                                                    ];
                                                }
                                            }

                                        }
                                    }

                                    return $carry;
                                }, [])
                        ),
                        'value_field' => array(
                            'title' => 'Form field',
                            'text' => 'text',
                            'allow_custom' => false,
                        ),
                    ),
                ),
            ),
        );
    }


    /**
     * Configures which columns should be displayed on the feed list page.
     *
     * @return array
     */
    public function feed_list_columns(): array
    {
        return array(
            'feedName' => esc_html__('Name', 'gravityformsmaileonfeed'),
            'transactionType' => esc_html__('Transaction type', 'gravityformsmaileonfeed'),
        );
    }

    /**
     * Format the value to be displayed in the mytextbox column.
     *
     * @param array $feed The feed being included in the feed list.
     *
     * @return string
     */
    public function get_column_value_mytextbox($feed)
    {
        return '<b>' . rgars($feed, 'meta/mytextbox') . '</b>';
    }

    /**
     * Prevent feeds being listed or created if an api key isn't valid.
     *
     * @return bool
     */
    public function can_create_feed()
    {
        return $this->is_valid_api_key();
    }

    /**
     * @return mixed
     */
    public function getPermissionChoices(): array
    {
        $permissions = $this->getAvailableContactPermissions();

        return array_reduce(array_keys($permissions), function ($carry, $key) use ($permissions) {
            $carry[] = ['label' => $key, 'value' => $permissions[$key]];

            return $carry;
        }, []);


    }


    private function maileon_get_encryption_key()
    {
        return get_option('maileon_encryption_key');
    }


    private function encrypt_token($data): string
    {
        $encryption_key = $this->maileon_get_encryption_key();
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted_data = openssl_encrypt($data, 'AES-256-CBC', $encryption_key, 0, $iv);

        return base64_encode($iv . $encrypted_data);
    }

    private function decrypt_token($data): string
    {
        $decryption_key = $this->maileon_get_encryption_key();
        $encrypted_data = base64_decode($data);
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($encrypted_data, 0, $iv_length);
        $encrypted_data = substr($encrypted_data, $iv_length);

        return openssl_decrypt($encrypted_data, 'AES-256-CBC', $decryption_key, 0, $iv);
    }


    public function is_valid_api_key(): bool
    {

        $settings = $this->get_plugin_settings();

        if (rgar($settings, 'maileon_api_key') == '') {
            return false;
        }

        $maileon = new MaileonService(['maileon_api_key' => $settings['maileon_api_key']]);
        $validate = $maileon->validateCredentials($settings['maileon_api_key']);


        return !!$validate;
    }


    /**
     * Configures the settings which should be rendered on the add-on settings tab.
     *
     * @return array
     */
    public function plugin_settings_fields(): array
    {
        $permissionChoices = $this->getPermissionChoices();


        return array(

            array(
                'title' => esc_html__('Configuration', 'gravityformsmaileonfeed'),
                'fields' => array(
                    array(
                        'label' => esc_html__('Enable Double Opt-In mailing', 'gravityformsmaileonfeed'),
                        'type' => 'checkbox',
                        'name' => 'doi_mailing',
                        'tooltip' => esc_html__('Enable if you want the double opt in mailing to be sent.', 'gravityformsmaileonfeed'),
                        'choices' => array(
                            array(
                                'label' => esc_html__('Enable Double Opt-In mailing', 'gravityformsmaileonfeed'),
                                'name' => 'doi_mailing',
                                'value' => true,
                            ),
                        ),
                    ),
                    array(
                        'label' => esc_html__('Permission', 'gravityformsmaileonfeed'),
                        'type' => 'select',
                        'name' => 'permission',
                        'tooltip' => esc_html__('Specify the default permission for contacts created by this Add-On.', 'gravityformsmaileonfeed'),
                        'choices' => $permissionChoices,
                    ),
                    array(
                        'name' => 'doi_mailing_key',
                        'tooltip' => esc_html__('The mailing key you want to be sent out when a new contact is created in Maileon. If empty, the one set as default in Maileon will be used.', 'gravityformsmaileonfeed'),
                        'label' => esc_html__('Double Opt-In mailing key', 'gravityformsmaileonfeed'),
                        'type' => 'text',
                        'class' => 'small',
                    ),
                ),
            ),
            array(
                'title' => esc_html__('API settings', 'gravityformsmaileonfeed'),
                'fields' => array(
                    array(
                        'name' => 'validApi',
                        'type' => 'hidden',
                        'class' => 'small',
                    ),
                    array(
                        'name' => 'maileon_api_key',
                        'label' => esc_html__('Maileon API Key', 'gravityformsmaileonfeed'),
                        'type' => 'text',
                        'required' => true,
                        'feedback_callback' => array($this, 'is_valid_api_key'),
                    ),
                ),
            ),
        );
    }


    private function convertType($field, $type)
    {
        switch ($type) {
            case 'boolean':
                return $field == true;
            case 'integer':
                return intval($field);
            case 'double':
                return (float)round($field, (strlen($field) - strpos($field, '.') - strpos($field, ',')));
            case 'string':
                return (string)$field;
            default:
                return $field;
        };
    }


    public function getAvailableContactPermissions()
    {
        $permissions = [
            'None' => 1,
            'Standard Opt-In' => 2,
            'Double Opt-In' => 4,
            'Double Opt-In+' => 5,
            'Other' => 6
        ];

//		$permissions = [];
//		for ( $i = 1; $i < 10; $i ++ ) {
//			if ( in_array( Permission::getPermission( $i ), $permissions ) ) {
//				break;
//			}
//			$permissions[] = Permission::getPermission( $i );
//		}

        return $permissions;
    }


    public function getTransactionFieldsByName($transactionType)
    {
        $maileon = new MaileonService(['maileon_api_key' => $this->get_plugin_setting('maileon_api_key')]);
        $transactionTypeFields = $maileon->getTransactionTypeByName($transactionType);
//		$this->log_debug( 'transactionType ' . json_encode( $transactionTypeFields ) );

        $typeMap = [];
        foreach ($transactionTypeFields->attributes as $transactionTypeField) {
            $typeMap[$transactionTypeField->name] = $transactionTypeField->type->value;
        }


        return $typeMap;
    }


    public function settings_transaction_type_select($field): ?string
    {
        $maileon = new MaileonService(['maileon_api_key' => $this->get_plugin_setting('maileon_api_key')]);
        $transactionTypes = $maileon->getTransactionTypes();
//		$this->log_debug( json_encode( $transactionTypes ) );
        $options = array(
            array(
                'label' => esc_html__('Select a transaction type', 'gravityformsmaileonfeed'),
                'value' => '',
            ),
        );
        foreach ($transactionTypes as $transactionType) {
            $options[] = array(
                'label' => esc_html($transactionType->name),
            );
        }
        $field['type'] = 'select';
        $field['choices'] = $options;
        $field['onchange'] = 'jQuery(this).parents("form").submit();';

        return $this->settings_select($field, false);
    }


    private function getAccount()
    {
        $api_key = $this->get_plugin_setting('maileon_api_key');

        $account = new AccountService(['API_KEY' => $api_key]);

        return $account->getAccountInfo();
    }

    public function sendHeartbeat(string $event): bool
    {
        try {
            $this->log_debug('heartbeat..');
            $account = $this->getAccount();
            $accountName = $account->getResult()->name;
            $this->log_debug($accountName);
            $params = [
                'pluginID' => '10015',
                'accountID' => $account->getResult()->id,
                'checkSum' => '28b9BBbwJ0Q_34XA',
                'clientHash' => $accountName[0] . $accountName[strlen($accountName) - 1],
                'event' => $event,
            ];
            $url = 'https://integrations.maileon.com/xsic/tx.php?';
            foreach ($params as $key => $value) {
                $url .= "$key=$value&";
            }
            $url = substr($url, 0, -1);
//			$this->log_debug( $url );
            $response = wp_remote_get($url);
            if (is_wp_error($response)) {
                $this->log_error('hearbeat error - ' . $response->get_error_message());

                return false;
            }
            $code = wp_remote_retrieve_response_code($response);
            $this->log_debug('hearbeat response code: ' . $code);

            return $code === 200;
        } catch (\Exception $e) {
            $this->log_error('hearbeat error - ' . $e->getMessage());

            return false;
        }
    }


    public function uninstall(): void
    {
        $this->sendHeartbeat('uninstall');
        Parent::uninstall();
        delete_option('gravityformsaddon_maileonfeedaddon_settings');
        delete_option('gravityformsaddon_maileonfeedaddon_version');
        delete_option('maileon_encryption_key');
    }


}








