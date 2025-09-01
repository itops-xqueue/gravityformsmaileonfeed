<?php

namespace App\includes;



use de\xqueue\maileon\api\client\contacts\Contact;
use de\xqueue\maileon\api\client\contacts\Contacts;
use de\xqueue\maileon\api\client\contacts\ContactsService;
use de\xqueue\maileon\api\client\contacts\Permission;
use de\xqueue\maileon\api\client\contacts\StandardContactField;
use de\xqueue\maileon\api\client\contacts\SynchronizationMode;
use de\xqueue\maileon\api\client\MaileonAPIException;
use de\xqueue\maileon\api\client\MaileonAPIResult;
use de\xqueue\maileon\api\client\transactions\AttributeType;
use de\xqueue\maileon\api\client\transactions\DataType;
use de\xqueue\maileon\api\client\transactions\TransactionsService;
use de\xqueue\maileon\api\client\transactions\TransactionType;
use de\xqueue\maileon\api\client\utils\PingService;
use GFAddOn;
use GFFeedAddOn;
use GFMaileonFeedAddOn;
use WpOrg\Requests\Exception;

class MaileonService extends GFFeedAddOn
{
	protected array $maileonConfig = [];

	protected ContactsService $contactsService;

	protected TransactionsService $transactionsService;

	private array $config;

	//    we use the config model as config
	public function __construct(array $config)
	{
		parent::__construct();
		$this->config = $config;

		$this->maileonConfig = [
			'BASE_URI' => 'https://api.maileon.com/1.0',
			'API_KEY'  => $config['maileon_api_key'],
			'TIMEOUT'  => 30,
		];

		$this->contactsService     = new ContactsService($this->maileonConfig);
		$this->transactionsService = new TransactionsService($this->maileonConfig);
		//        $this->transactionsService->setDebug(true);
	}

	/**
	 * Check the Maileon API key is valid or not
	 */

	public function validateCredentials($apiKey): bool
	{

		$config = [
			'BASE_URI' => 'https://api.maileon.com/1.0',
			'API_KEY'  => $apiKey,
			'TIMEOUT'  => 30,
		];

		$pingService = new PingService($config);

		try {
			$response = $pingService->pingGet();
		} catch (MaileonAPIException $e) {
			//			Log::error( $e->getMessage(), [ 'Class' => get_class( $this ), 'Method' => __FUNCTION__ ] );
			$this->log_debug($e->getMessage());

			return false;
		}

		return $response->isSuccess();
	}


	/**
	 * Create the Contact at Maileon
	 */
	public function subscribeContactToMaileon(Contact $contact, bool $justTransaction, bool $needsDoi, string $doiKey)
	{
		$settings = GFAddOn::get_plugin_settings();
		print_r($settings);
		$this->log_debug(json_encode($settings));
		if (! empty($contact->custom_fields)) {
			$this->checkCustomFields($contact->custom_fields);
		}

		if ($this->contactExistsWithValidPermission($contact->email)) {
			$needDOIProcess = false;
			$DOIKey         = '';
		} else {
			$needDOIProcess = $needsDoi;
			$DOIKey         = $doiKey;

			$this->log_debug('$needDOIProcess ' . $needDOIProcess);
			$this->log_debug('$DOIKey ' . $DOIKey);
		}

		if ($justTransaction) {
			$needDOIProcess = false;
			$DOIKey         = '';
		}
		try {
			$response = $this->contactsService->createContact(
				$contact,
				SynchronizationMode::$UPDATE,
				'GravityForms',
				$contact->subscribtionPage,
				$needDOIProcess,
				$needDOIProcess,
				$DOIKey
			);
		} catch (MaileonAPIException $response) {
			$this->log_error($response->getMessage());

			return $response->getMessage();
		}

		return ($response->isSuccess() ?: $response->getResultXML()->message ?: $response->getResult());
	}

	/**
	 * Check the contact exist with valid permission at Maileon
	 */
	public function contactExistsWithValidPermission(string $email): bool
	{
		try {
			$response = $this->contactsService->getContactByEmail($email);
			if ($response->getStatusCode() !== 404) {
				$contact    = $response->getResult();
				$permission = $contact->permission->getCode();

				$valid_codes = [2, 3, 4, 5];

				if (in_array($permission, $valid_codes)) {
					return true;
				}
			}

			return false;
		} catch (MaileonAPIException $e) {
			$this->log_error($e->getMessage());

			return false;
		}
	}

	public function getPermissionCodeOfExistingContact(string $email)
	{
		$response = $this->contactsService->getContactByEmail($email);

		return $response->isSuccess() ? $response->getResult()->permission->getCode() : -1;
	}

	public function syncContacts(Contacts $contacts): bool
	{

		try {
			$response = $this->contactsService->synchronizeContacts(
				$contacts,
				Permission::getPermission($this->config->initial_permission),
				SynchronizationMode::$UPDATE,
				false,
				true,
				false,
				false
			);

			return $response->isSuccess();
		} catch (MaileonAPIException $e) {
			$this->log_error($e->getMessage(), ['Class' => get_class($this), 'Method' => __FUNCTION__]);

			return false;
		}
	}

	public function getStandardFields()
	{
		return [
			StandardContactField::$ADDRESS      => ["0" => 'integer'],
			StandardContactField::$BIRTHDAY     => ["0" => 'date'],
			StandardContactField::$CITY         => ["0" => 'string'],
			StandardContactField::$COUNTRY      => ["0" => 'string'],
			StandardContactField::$FIRSTNAME    => ["0" => 'string'],
			StandardContactField::$FULLNAME     => ["0" => 'string'],
			//			StandardContactField::$GENDER            => [ "0" => 'string' ],
			StandardContactField::$HNR          => ["0" => 'string'],
			StandardContactField::$LASTNAME     => ["0" => 'string'],
			//			StandardContactField::$LOCALE            => [ "0" => 'string' ],
			StandardContactField::$NAMEDAY      => ["0" => 'string'],
			StandardContactField::$ORGANIZATION => ["0" => 'string'],
			StandardContactField::$REGION       => ["0" => 'string'],
			StandardContactField::$SALUTATION   => ["0" => 'string'],
			StandardContactField::$TITLE        => ["0" => 'string'],
			StandardContactField::$ZIP          => ["0" => 'string'],
			StandardContactField::$STATE        => ["0" => 'string'],
			//			StandardContactField::$SENDOUT_STATUS    => [ "0" => 'string' ],
			//			StandardContactField::$PERMISSION_STATUS => [ "0" => 'string' ],
		];
	}

	public function getCustomFields()
	{
		$customFields = $this->contactsService->getCustomFields();
		if ($customFields->isSuccess()) {

			return (array) json_decode(json_encode($customFields->getResult()->custom_fields), true);
		}

		return [];
	}


	/**
	 * Check the custom fields exist at Maileon, if not create it.
	 */
	public function checkCustomFields(array $customFields): bool
	{
		try {
			$customFieldsResponse    = $this->contactsService->getCustomFields();
			$customFieldsFromMaileon = $customFieldsResponse->getResult();
		} catch (MaileonAPIException $e) {

			$this->log_error($e->getMessage());

			return false;
		}


		foreach ($customFields as $fieldName => $fieldValue) {
			if (! array_key_exists($fieldName, $customFieldsFromMaileon->custom_fields)) {
				$this->contactsService->createCustomField($fieldName);
			}
		}

		return true;
	}

	/**
	 * Check the Contact exist at Maileon
	 */
	public function existsContact(string $email): bool
	{
		try {
			$response = $this->contactsService->getContactByEmail($email);
		} catch (MaileonAPIException $e) {
			$this->log_error($e->getMessage());

			return false;
		}

		return $response->isSuccess();
	}

	/**
	 * Unsubscribe the Contact from Maileon
	 */
	public function unsubscribeMaileonContact(string $email): bool
	{
		try {
			$response = $this->contactsService->unsubscribeContactByEmail($email);
		} catch (MaileonAPIException $e) {
			$this->log_error($e->getMessage());

			return false;
		}

		return $response->isSuccess();
	}

	/**
	 * Check the Transaction Type exist at Maileon
	 */
	public function existsTransactionType(string $transactionName): bool
	{
		try {
			$response = $this->transactionsService->getTransactionTypeByName($transactionName);
		} catch (MaileonAPIException $e) {
			$this->log_error($e->getMessage());

			return false;
		}

		if ($response->getStatusCode() === 404) {
			return false;
		}

		return true;
	}

	/**
	 * Create the Transaction Type
	 */
	public function setTransactionType(string $transactionName): bool
	{
		$transactionType       = new TransactionType();
		$transactionType->name = $transactionName;

		try {
			$transactionType->attributes = $this->createTransactionTypeAttributes($transactionName);
		} catch (Exception $e) {
			$this->log_error($e->getMessage());

			return false;
		}

		try {
			$result = $this->transactionsService->createTransactionType($transactionType);
		} catch (MaileonAPIException $e) {
			$this->log_error($e->getMessage());

			return false;
		}

		return $result->isSuccess();
	}

	/**
	 * Send transactions to Maileon
	 */
	public function sendTransactions(array $transactions): bool
	{
		try {
			$response = $this->transactionsService->createTransactions($transactions);
		} catch (MaileonAPIException $e) {
			$this->log_error($e->getMessage());

			return false;
		}
		if (! $response->isSuccess()) {

			$this->log_error($response->getBodyData());

			return false;
		}

		return $response->isSuccess();
	}


	/**
	 * Get available transaction types
	 */
	public function getTransactionTypes()
	{
		try {
			$response = $this->transactionsService->getTransactionTypes(1,200);

			return $response->getResult();
		} catch (MaileonAPIException $e) {
			$this->log_error($e);

			return [];
		}
	}

	public function getTransactionTypeByName($transactionTypeName)
	{
		try {
			$response = $this->transactionsService->getTransactionTypeByName($transactionTypeName);

			return $response->getResult();
		} catch (MaileonAPIException $e) {
			$this->log_error($e);

			return [];
		}
	}


	/**
	 * Set the Transaction Type attributes from the config
	 *
	 * @throws InvalidTransactionName
	 */
	private function createTransactionTypeAttributes(string $transactionName)
	{
		$transactionAttributes = [];
		switch ($transactionName) {
			case 'predefinedtransactionname_v1':
				$attributesFromConfig = config('plugin.predefinedtransactionname_v1');

			default:
				throw new InvalidTransactionName();
				break;
		}




		foreach ($attributesFromConfig as $name => $type) {
			$transactionAttributes[] = new AttributeType(null, $name, DataType::getDataType($type), false);
		}

		return $this->addGenericAttributes($transactionAttributes);
	}

	/**
	 * Set the generic attributes from the config
	 */
	private function addGenericAttributes(array $transactionAttributes): array
	{
		$genericAttributesFromConfig = config('plugin.maileonGenericFields');

		foreach ($genericAttributesFromConfig as $name => $type) {
			$transactionAttributes[] = new AttributeType(null, $name, DataType::getDataType($type), false);
		}

		return $transactionAttributes;
	}
}
