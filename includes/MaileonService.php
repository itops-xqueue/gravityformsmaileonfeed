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
	 * This class has no add-on slug of its own, so Gravity Forms would drop its log messages.
	 * Write them to the Maileon add-on's log instead.
	 *
	 * @param string $message
	 */
	public function log_error($message)
	{
		GFMaileonFeedAddOn::get_instance()->log_error(is_scalar($message) ? (string) $message : json_encode($message));
	}

	/**
	 * @param string $message
	 */
	public function log_debug($message)
	{
		GFMaileonFeedAddOn::get_instance()->log_debug(is_scalar($message) ? (string) $message : json_encode($message));
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
	/**
	 * @param string $subscriptionPage sent to Maileon as the subscription page (the feed name). Passed
	 *                                 separately because newer API clients don't allow extra properties on Contact.
	 */
	public function subscribeContactToMaileon(Contact $contact, bool $justTransaction, bool $needsDoi, string $doiKey, string $subscriptionPage = '')
	{
		if (! empty($contact->custom_fields)) {
			$this->checkCustomFields($contact->custom_fields);
		}

		$existingPermission = $this->getPermissionCodeOfExistingContact($contact->email);

		// Maileon already refuses to downgrade permission 4/5 to anything else, and SOI/COI to
		// "none", but it does allow 5 -> 4, which is still a downgrade we don't want, so guard it here.
		if ($existingPermission === 5 && $contact->permission->getCode() === 4) {
			$contact->permission = Permission::getPermission(5);
		}

		if (in_array($existingPermission, [4, 5], true)) {
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
				$subscriptionPage,
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
	 * Get the permission code of an existing contact at Maileon, or -1 if the contact doesn't
	 * exist or the lookup fails.
	 */
	public function getPermissionCodeOfExistingContact(string $email)
	{
		try {
			$response = $this->contactsService->getContactByEmail($email);

			return $response->isSuccess() ? $response->getResult()->permission->getCode() : -1;
		} catch (MaileonAPIException $e) {
			$this->log_error($e->getMessage());

			return -1;
		}
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

	/**
	 * Get the custom contact fields defined in Maileon, keyed by field name.
	 *
	 * Always returns an array: on PHP 8+, a non-array here (e.g. after a 401/403/429) would make
	 * the feed settings page fatal.
	 *
	 * @return array
	 */
	public function getCustomFields(): array
	{
		try {
			$response = $this->contactsService->getCustomFields();
		} catch (\Throwable $e) {
			$this->log_error(__METHOD__ . ' - ' . $e->getMessage());

			return [];
		}

		if (! $response->isSuccess()) {
			$this->logFailedResponse(__METHOD__, $response);

			return [];
		}

		$result = $response->getResult();
		if (! is_object($result) || ! isset($result->custom_fields)) {
			return [];
		}

		$customFields = json_decode(json_encode($result->custom_fields), true);

		return is_array($customFields) ? $customFields : [];
	}


	/**
	 * Check the custom fields exist at Maileon, if not create it.
	 */
	public function checkCustomFields(array $customFields): bool
	{
		try {
			$customFieldsResponse = $this->contactsService->getCustomFields();
		} catch (\Throwable $e) {
			$this->log_error(__METHOD__ . ' - ' . $e->getMessage());

			return false;
		}

		$customFieldsFromMaileon = $customFieldsResponse->getResult();
		if (! $customFieldsResponse->isSuccess() || ! is_object($customFieldsFromMaileon) || ! is_array($customFieldsFromMaileon->custom_fields ?? null)) {
			// Without the existing field list we can't tell which fields are missing, so don't create any.
			$this->logFailedResponse(__METHOD__, $customFieldsResponse);

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
			$this->logFailedResponse(__METHOD__, $response);

			return false;
		}

		return $response->isSuccess();
	}


	/**
	 * Get available transaction types.
	 *
	 * Always returns an array: on a 401/403/429 the API client returns false or the raw body,
	 * which made array_unshift() in the feed settings fatal on PHP 8+.
	 *
	 * @return array
	 */
	public function getTransactionTypes(): array
	{
		try {
			$response = $this->transactionsService->getTransactionTypes(1, 200);
		} catch (\Throwable $e) {
			$this->log_error(__METHOD__ . ' - ' . $e->getMessage());

			return [];
		}

		if (! $response->isSuccess()) {
			$this->logFailedResponse(__METHOD__, $response);

			return [];
		}

		$result = $response->getResult();

		return is_array($result) ? $result : [];
	}

	/**
	 * Get a transaction type by name, or null if it can't be fetched.
	 *
	 * @return object|null
	 */
	public function getTransactionTypeByName($transactionTypeName)
	{
		try {
			$response = $this->transactionsService->getTransactionTypeByName($transactionTypeName);
		} catch (\Throwable $e) {
			$this->log_error(__METHOD__ . ' - ' . $e->getMessage());

			return null;
		}

		if (! $response->isSuccess()) {
			$this->logFailedResponse(__METHOD__, $response);

			return null;
		}

		$result = $response->getResult();

		return is_object($result) ? $result : null;
	}

	/**
	 * Log a non-successful API response with its HTTP status, so permission problems
	 * (e.g. an API key without access to transactions) show up in the add-on log.
	 *
	 * @param string $method
	 * @param mixed  $response MaileonAPIResult
	 */
	private function logFailedResponse(string $method, $response): void
	{
		$status = is_object($response) && method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 'n/a';
		$body   = is_object($response) && method_exists($response, 'getBodyData') ? (string) $response->getBodyData() : '';

		$hint = in_array((int) $status, [401, 403], true) ? ' Check that the API key is valid and has permission for this resource.' : '';

		$this->log_error(sprintf(
			'%s - Maileon API request failed (HTTP %s).%s %s',
			$method,
			$status,
			$hint,
			substr($body, 0, 500)
		));
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
