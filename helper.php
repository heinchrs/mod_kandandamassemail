<?php

/**
 * @package    KandandaMassEmail
 * @author     Heinl Christian <heinchrs@gmail.com>
 * @copyright  (C) 2015-2020 Heinl Christian
 * @license    GNU General Public License version 2 or later
 */

// -- No direct access
defined('_JEXEC') or die;

JLoader::register('JFile', JPATH_LIBRARIES . '/joomla/filesystem/file.php');

/**
 * ini_set('display_errors', 1);
 * ini_set('display_startup_errors', 1);
 * error_reporting(E_ALL);
 */
ini_set("mail.log", "/logs/mail.log");
ini_set("mail.add_x_header", true);


/**
 * Helper class to extract form data, get email receivers out of selected Kandanda
 * groups and select fields and send a specific email to all of the corresponding
 * Kandanda members.
 *
 * @author  Heinl Christian <heinchrs@gmail.com>
 * @since   1.0
 */
class KandandaMassEmail
{
	/**
	 * Array which holds all selected Kandanda groups id's to which members emails should be sent to
	 * @var array
	 */
	private $receiverGroup;

	/**
	 * Array which holds assoc arrays containing selectFieldId of Kandanda field and array with selected values out of Kandanda Select field
	 * @var array
	 */
	private $receiverSelect;

	/**
	 * Boolean which holds the status of "all members" checkbox
	 * @var boolean
	 */
	private $allMembers;

	/**
	 * String which holds the email subject for sending email
	 * @var string
	 */
	private $subject;

	/**
	 * String which holds the email content for sending email
	 * @var string
	 */
	private $content;

	/**
	 * Configuration parameter of module
	 * @var array
	 */
	private $params;

	/**
	 * The received form data
	 * @var string
	 */
	private $formData;

	/**
	 * Constructor
	 * @param   array $params Module configuration parameter
	 */
	public function __construct($params)
	{
		$this->params = $params;
	}

	/**
	 * Evaluates form data and send emails to all corresponding Kandanda members
	 * which are assigned to the selected Kandanda groups or select fields
	 *
	 * @return  void
	 */
	public function processFormData()
	{
		// Extract form data and store it in member variables
		$this->getFormInput();

		// Get all email addresses of Kandanda members which are assigned to the selected groups
		$mailAccounts = $this->getMailAccounts();

		if (isset($mailAccounts))
		{
			// Lower case all mail addresses
			foreach ($mailAccounts as &$receiver)
			{
				/**
				 * $receiver['Email'] = strtolower($receiver['Email']);
				 */
				$recipient[] = strtolower($receiver['Email']);

				// Check if gmx, web or online email addresses exists -> needs to be sent in single mails due to problems with GMX and WEB
				if (preg_match('/@gmx\.(?:de|net)|@online\.de|@web\.de/', $receiver['Email']))
				{
					$singleMailAddresses[] = strtolower($receiver['Email']);
				}
				else
				{
					$multipleMailAddresses[] = strtolower($receiver['Email']);
				}
			}

			// Array which holds all emails which must be sent with only one address in BCC field
			$singleMailAddresses = array_unique($singleMailAddresses);

			// Array which holds all emails which can be sent at once all together as BCC recipients
			$multipleMailAddresses = array_unique($multipleMailAddresses);

			// Array which holds all mail accounts
			$recipient = array_unique($recipient);
		}

		// If debug mode is selected
		if (($this->params->get('debug_output', '') == 1) && ( (count((array) $multipleMailAddresses) > 0) || (count((array) $singleMailAddresses) > 0) ))
		{
			print ("<h2>Mailaccounts addressed via one mail each as BCC receiver</h2>");
			print ("<pre>");
			print_r($multipleMailAddresses);
			print ("</pre>");

			print ("<h2>Mailaccounts addressed via separate mails as TO receiver</h2>");
			print ("<div>due to restrictions of GMX.de and WEB.de</div>");
			print ("<pre>");
			print_r($singleMailAddresses);
			print ("</pre>");
		}
		else
		{
			// If at least one email address has been found
			if (count((array) $mailAccounts) > 0)
			{
				$mailer = JFactory::getMailer();
				$config = JFactory::getConfig();
				$sender = array(
					$config->get('config.mailfrom'),
					$config->get('config.fromname'));
				$mailer->setSender($sender);

				$mailer->setSubject($this->subject);
				$mailer->isHTML(true);
				$mailer->Encoding = 'base64';

				$body = "";

				// Check if a header image is specified in parameter setting of module
				if (!empty($this->params->get('header_image_url', '')))
				{
					// Add header image to email body
					$body .= "<img id=\"header_img\" src=\"" . $this->params->get('header_image_url', '') . "\" alt=\"Homepage-Logo\" /><br/><br/>";
				}

				// Add form content to email body
				$body .= $this->content;
				$mailer->setBody($body);

				// Body in plain text for non-HTML mail clients
				$mailer->AltBody = strip_tags($this->content);

				$user = JFactory::getUser();
				$mailer->addReplyTo($user->email, $user->name);

				// Init flag, that mail sending was OK
				$mailSendingOk = true;

				// If mail addresses exists which can be set via BCC receiver
				if (count($multipleMailAddresses) > 0)
				{
					$mailer->addBCC($multipleMailAddresses);

					// Send one mail with all receivers who are not GMX or WEB accounts
					$send = $mailer->Send();

					if ($send !== true)
					{
						JError::raiseWarning(100, JText::_('MOD_KANDANDA_MASSMAIL_MAIL_FAILED'));

						// Set flag, that mail sending was not ok
						$mailSendingOk = false;
					}
				}

				// Send for all mails containing GMX or WEB accounts one mail with only one recipient
				foreach ($singleMailAddresses as $addr)
				{
					$mailer->clearAllRecipients();
					$mailer->addRecipient($addr);

					$send = $mailer->Send();

					if ($send !== true)
					{
						JError::raiseWarning(100, JText::_('MOD_KANDANDA_MASSMAIL_MAIL_FAILED'));
						$mailSendingOk = false;
						break;
					}
				}

				// Check if all mails were correctly sent
				if ($mailSendingOk)
				{
					JFactory::getApplication()->enqueueMessage(JText::_('MOD_KANDANDA_MASSMAIL_MAIL_SUCCEEDED'), 'notice');
				}

				// If a notification mail address is configured in module parameters
				if ($this->params->get('notification_email', '') != '')
				{
					$mailer->ClearAllRecipients();
					$mailer->addRecipient($this->params->get('notification_email', ''));
					$user = JFactory::getUser();
					$body = utf8_decode(JText::sprintf('MOD_KANDANDA_MASSMAIL_MAIL_NOTIFICATION_MAIL', $user->name, $user->username, $user->email, $this->content, implode("<br/>", $recipient)));
					$mailer->setBody($body);
					$send = $mailer->Send();
				}
			}
		}
	}

	/**
	 * Get all data out of email form and store it in class attributes
	 *
	 * @return void
	 */
	private function getFormInput()
	{
		// Get form input
		$input = JFactory::getApplication()->input;

		// Get the form data
		$this->formData = new JRegistry($input->get('kandanda_massmail_form_fields', '', 'array'));

		// Get all selected Kandanda groups to which members emails should be sent to
		$this->receiverGroup = $this->formData->get('receiver_group');

		// Get all Kandanda select field ids which are marked in module configuration
		$selectFieldIds = $this->params->get('kandanda_selects', '');

		if (is_array($selectFieldIds) && count($selectFieldIds) > 0)
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);

			foreach ($selectFieldIds as $value)
			{
				$query->clear();
				$query->select('*');
				$query->from('#__kandanda_fields');
				$query->where('id = ' . $value);
				$db->setQuery($query);

				// Result holds assoc array of kandanda select field configuration data
				$result = $db->loadAssoc();

				// Create assoc array which holds the Kandanda select field id and the corresponding selected elements from frontend form
				$this->receiverSelect[] = array('field_id' => $value, 'selection' => $this->formData->get('receiver_select_' . $result['alias']));
			}
		}

		// Get status of checkbox "all members"
		$this->allMembers = $this->formData->get('all_members');

		// Get subject for email out of form
		$this->subject = $this->formData->get('email_subject');

		// Get email content out of form
		$this->content = $this->formData->get('email_content');
	}

	/**
	 * Get email addresses of Kandanda members which are assigned to the
	 * selected Kandanda groups or Kandanda Select fields
	 *
	 * @return assoc array with Kandanda member data containing member id,
	 * email address, lastname and firstname
	 */
	private function getMailAccounts()
	{
		// If email should be sent to all Kandanda members
		if ($this->allMembers == 1)
		{
			// Get mail accounts from all Kandanda members
			$mailAccounts = $this->getMailAccountsFromAllMembers();
		}
		else
		{
			// Get mail accounts from Kandanda members which are assigned to selected groups
			$mailAccounts = $this->getMailAccountsFromGroups();

			// Get mail accounts from Kandanda members which are assigned to selected Kandanda select fields
			$mailAccountsSelect = $this->getMailAccountsFromSelect();

			// If mail accounts assigned to select fields exists -> add it to $mailAccounts array
			if (is_array($mailAccountsSelect))
			{
				foreach ($mailAccountsSelect as $entry)
				{
					// Check if mail account is not already
					if ($this->recursiveArraySearch($entry['MemberID'], $mailAccounts) === false)
					{
						$mailAccounts[] = $entry;
					}
				}
			}
		}

		// Check if additional email accounts exists and add it to $mailAccounts array
		$additionalMail = explode(",", $this->formData->get('additional_email'));

		if (is_array($additionalMail) && count($additionalMail) > 0)
		{
			foreach ($additionalMail as $entry)
			{
				if ($entry != "" && $this->recursiveArraySearch($entry, $mailAccounts) === false)
				{
					$array = array(
						"MemberID" => "-1",
						"Name" => "",
						"Vorname" => "",
						"Email" => $entry);
					$mailAccounts[] = $array;
				}
			}
		}

		return $mailAccounts;
	}

	/**
	 * Get email addresses of Kandanda members which are assigned to the selected
	 * Kandanda groups.
	 *
	 * @return assoc array with Kandanda member data containing member id,
	 * email address, lastname and firstname
	 */
	private function getMailAccountsFromGroups()
	{
		if (count((array) $this->receiverGroup) > 0)
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->clear();

			foreach ($this->receiverGroup as $value)
			{
				$sSQL[] = $db->quoteName('c.id') . ' = ' . $value;
			}

			// Get module parameter for id of Kandanda field which contains the email adress of a member
			$emailFieldId = $this->params->get('email_field', '');

			$query->select($db->quoteName(array('a.member_id', 'f.value', 'h.value', 'k.value'), array('MemberID', 'Name', 'Vorname', 'Email')));
			$query->from($db->quoteName('#__kandanda_fields_values', 'f'));
			$query->join('LEFT', $db->quoteName('#__kandanda_accounts', 'a') . ' ON (' . $db->quoteName('a.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
			$query->join('LEFT', $db->quoteName('#__kandanda_member_category_map', 'm') . ' ON (' . $db->quoteName('m.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
			$query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON (' . $db->quoteName('m.catid') . ' = ' . $db->quoteName('c.id') . ' AND (' . implode(' OR ', $sSQL) . '))');

			// Field id 1 is always Kandanda fixed field 'firstname'
			$query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'h') . ' ON (' . $db->quoteName('h.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('h.field_id') . '= 1)');
			$query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'k') . ' ON (' . $db->quoteName('k.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('k.field_id') . '= ' . $emailFieldId . ' AND ' . $db->quoteName('k.value') . ' <> \'\')');
			$query->where($db->quoteName('published') . ' = ' . $db->quote('1'));

			// Field id 2 is always Kandanda fixed field 'lastname'
			$query->where($db->quoteName('f.field_id') . ' = ' . $db->quote('2'));
			$query->order($db->quoteName('Name'));
			$query->order($db->quoteName('Vorname'));
			$query->group($db->quoteName('Name'));
			$query->group($db->quoteName('Vorname'));

			$db->setQuery($query);
			$result = $db->loadAssocList();

			return $result;
		}
	}

	/**
	 * Get email addresses of Kandanda members which are assigned to the selected
	 * Kandanda select fields.
	 *
	 * @return assoc array with Kandanda member data containing member id,
	 * email address, lastname and firstname
	 */
	private function getMailAccountsFromSelect()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$result = array();

		if (is_array($this->receiverSelect))
		{
			foreach ($this->receiverSelect as $kandandaSelect)
			{
				$selectFieldId = $kandandaSelect['field_id'];
				$receiver = $kandandaSelect['selection'];

				// Get module parameter for id of Kandanda field which contains the email adress of a member
				$emailFieldId = $this->params->get('email_field', '');

				if (count((array) $receiver) > 0)
				{
					$query->clear();

					/**
					 * Values of Kandanda select fields are stored separated by linefeed('\n')
					 * So the regex should match the first entry after begin of Kandanda select field value
					 * or the first entry after a line feed character.
					 * The regex syntax is used to avoid listing entries which contains the searched value as a sub part
					 * So only entries which contains the whole search entry at the beginning of a line
					 */
					foreach ($receiver as $value)
					{
						$sSQL[] = $db->quoteName('i.value') . ' REGEXP "(^|\n)' . $value . '"';
					}

					$query->select($db->quoteName(array('a.member_id', 'f.value', 'h.value', 'k.value'), array('MemberID', 'Name', 'Vorname', 'Email')));
					$query->from($db->quoteName('#__kandanda_fields_values', 'f'));
					$query->join('LEFT', $db->quoteName('#__kandanda_accounts', 'a') . ' ON (' . $db->quoteName('a.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
					$query->join('LEFT', $db->quoteName('#__kandanda_members', 'g') . ' ON (' . $db->quoteName('g.id') . ' = ' . $db->quoteName('a.member_id') . ')');
					$query->join('LEFT', $db->quoteName('#__kandanda_member_category_map', 'm') . ' ON (' . $db->quoteName('m.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
					$query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON (' . $db->quoteName('m.catid') . ' = ' . $db->quoteName('c.id') . ')');

					// Field id 1 is always Kandanda fixed field 'firstname'
					$query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'h') . ' ON (' . $db->quoteName('h.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('h.field_id') . '= 1)');
					$query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'k') . ' ON (' . $db->quoteName('k.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('k.field_id') . '= ' . $emailFieldId . ' AND ' . $db->quoteName('k.value') . ' <> \'\')');
					$query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'i') . ' ON (' . $db->quoteName('i.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('i.field_id') . '= ' . $selectFieldId . ' AND (' . implode(' OR ', $sSQL) . '))');
					$query->where($db->quoteName('g.published') . ' = ' . $db->quote('1'));

					// Field id 2 is always Kandanda fixed field 'lastname'
					$query->where($db->quoteName('f.field_id') . ' = ' . $db->quote('2'));
					$query->order($db->quoteName('Name'));
					$query->order($db->quoteName('Vorname'));
					$query->group($db->quoteName('Name'));
					$query->group($db->quoteName('Vorname'));

					$db->setQuery($query);
					$result = array_merge($result, $db->loadAssocList());
				}
			}
		}

		return $result;
	}

	/**
	 * Get email addresses of all Kandanda members
	 *
	 * @return assoc array with Kandanda member data containing member id,
	 * email address, lastname and firstname
	 */
	private function getMailAccountsFromAllMembers()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->clear();

		// Get module parameter for id of Kandanda field which contains the email adress of a member
		$emailFieldId = $this->params->get('email_field', '');

		$query->select($db->quoteName(array('a.member_id', 'f.value', 'h.value', 'k.value'), array('MemberID', 'Name', 'Vorname', 'Email')));
		$query->from($db->quoteName('#__kandanda_fields_values', 'f'));
		$query->join('LEFT', $db->quoteName('#__kandanda_accounts', 'a') . ' ON (' . $db->quoteName('a.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
		$query->join('LEFT', $db->quoteName('#__kandanda_members', 'g') . ' ON (' . $db->quoteName('g.id') . ' = ' . $db->quoteName('a.member_id') . ')');
		$query->join('LEFT', $db->quoteName('#__kandanda_member_category_map', 'm') . ' ON (' . $db->quoteName('m.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
		$query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON (' . $db->quoteName('m.catid') . ' = ' . $db->quoteName('c.id') . ')');

		// Field id 1 is always Kandanda fixed field 'firstname'
		$query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'h') . ' ON (' . $db->quoteName('h.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('h.field_id') . '= 1)');
		$query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'k') . ' ON (' . $db->quoteName('k.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('k.field_id') . '= ' . $emailFieldId . ' AND ' . $db->quoteName('k.value') . ' <> \'\')');
		$query->where($db->quoteName('g.published') . ' = ' . $db->quote('1'));

		// Field id 2 is always Kandanda fixed field 'lastname'
		$query->where($db->quoteName('f.field_id') . ' = ' . $db->quote('2'));
		$query->order($db->quoteName('Name'));
		$query->order($db->quoteName('Vorname'));
		$query->group($db->quoteName('Name'));
		$query->group($db->quoteName('Vorname'));

		$db->setQuery($query);
		$result = $db->loadAssocList();

		return $result;
	}

	/**
	 * Searches in all values of an array for a specific value
	 *
	 * @param   type   $needle    Value to search for
	 * @param   array  $haystack  Array to search in
	 * @return  boolean
	 */
	private function recursiveArraySearch($needle, $haystack)
	{
		if (is_array($haystack))
		{
			foreach ($haystack as $key => $value)
			{
				$currentKey = $key;

				if (($needle === $value) || ( is_array($value) && $this->recursiveArraySearch($needle, $value) !== false))
				{
					return $currentKey;
				}
			}
		}

		return false;
	}

	/**
	 * Uploads file
	 *
	 * @param   string  $key                Filename of file to upload
	 *
	 * @param   string  $destinationFolder  Destination path
	 *
	 * @return  string  Pathinformation of uploaded file
	 */
	private function getFile($key, $destinationFolder)
	{
		/**
		 *  now let's process uploads: the array files contains a key "$key" which is the key name.
		 *  we need to copy the files uploaded
		 *  (if any are there and if they match the field filter = pdf)
		 *  and set the data->pdf to its new path.
		 * */
		$file = JRequest::getVar('jform', array(), 'files', 'array');

		if ($file['error'][$key] != "0")
		{
			error_log('no files uploaded, exiting now');

			return "";
		}

		/**
		 * error_log('OFFER FOUND FILES '.var_export($file,true));
		 */

		$tempName = $file['tmp_name'][$key];
		$tempFullPath = ini_get('upload_tmp_dir') . $tempName;
		$type = $file['type'][$key];
		$name = $file['name'][$key];

		/**
		 * error_log('DATA FOUND: '. "temp: $tempName , type: $type, name: $name");
		 */

		if (file_exists($tempFullPath))
		{
			if (mkdir(JPATH_SITE . $destinationFolder, 0755, true))
			{
				if (copy($source = $tempFullPath, $dest = JPATH_SITE . $destinationFolder . "/" . $name))
				{
					return $destinationFolder . "/" . $name;
				}
				else
				{
					error_log('could not copy ' . "$source to $dest");
				}
			}
			else
			{
				error_log('could not create folder ' . JPATH_SITE . $destinationFolder);
			}

			return "";
		}
		else
		{
			error_log('FILE NOT FOUND: ' . $tempFullPath);
		}
	}
}
