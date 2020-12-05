<?php

/**
 * @package    KandandaMassEmail
 * @author     Heinl Christian <heinchrs@gmail.com>
 * @license    GNU General Public License version 2 or later 
 */
//-- No direct access
defined('_JEXEC') or die;

JLoader::register('JFile', JPATH_LIBRARIES . '/joomla/filesystem/file.php');

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
ini_set("mail.log", "/logs/mail.log");
ini_set("mail.add_x_header", TRUE);


/**
 * Helper class to extract form data, get email receivers out of selected Kandanda 
 * groups and select fields and send a specific email to all of the corresponding
 * Kandanda members.
 *
 * @author Heinl Christian
 */
class KandandaMassEmail {

    //array which holds all selected Kandanda groups id's to which members emails should be sent to
    private $receiver_group;
    //array which holds assoc arrays containing select_field_id of Kandanda field and array with selected values out of Kandanda Select field
    private $receiver_select;
    //boolean which holds the status of "all members" checkbox
    private $all_members;
    //string which holds the email subject for sending email
    private $subject;
    //string which holds the email content for sending email
    private $content;
    //configuration parameter of module
    private $params;
    //The received form data
    private $form_data;

    /**
     * Constructor
     * @param array $params Module configuration parameter
     */
    function KandandaMassEmail($params) {
        $this->params = $params;
    }

    /**
     * evaluates form data and send emails to all corresponding Kandanda members
     * which are assigned to the selected Kandanda groups or select fields
     */
    public function processFormData() {
        //extract form data and store it in member variables
        $this->getFormInput();

        //get all email addresses of Kandanda members which are assigned to the selected groups
        $MailAccounts = $this->getMailAccounts();
        if (isset($MailAccounts))
        {
          //lower case all mail addresses
          foreach ($MailAccounts as &$receiver) {
            //$receiver['Email'] = strtolower($receiver['Email']);
            $recipient[] = strtolower($receiver['Email']);
            
            //check if gmx, web or online email addresses exists -> needs to be sent in single mails due to problems with GMX and WEB
            if (preg_match('/@gmx\.(?:de|net)|@online\.de|@web\.de/', $receiver['Email'])) {
	          $single_mail_addresses[] = strtolower($receiver['Email']);
            }
            else {
              $multiple_mail_addresses[] = strtolower($receiver['Email']);
            }
          }
		  //array which holds all emails which must be sent with only one address in BCC field
          $single_mail_addresses = array_unique($single_mail_addresses);
		  //array which holds all emails which can be sent at once all together as BCC recipients
          $multiple_mail_addresses = array_unique($multiple_mail_addresses);
		  //array which holds all mail accounts
          $recipient = array_unique($recipient);
        }

        //if debeug mode is selected
        if ($this->params->get('debug_output', '') == 1) {
            print ("<pre>");
            //print_r($MailAccounts);
            print_r($single_mail_addresses);
            print ("</pre>");
        } else {
            //if at least one email address has been found
            if (count((array)$MailAccounts) > 0) {
                $mailer = JFactory::getMailer();
                $config = JFactory::getConfig();
                $sender = array(
                    $config->get('config.mailfrom'),
                    $config->get('config.fromname'));
                $mailer->setSender($sender);                
                //foreach ($MailAccounts as $receiver) {
                //    $recipient[] = $receiver['Email'];
                //}
                $mailer->setSubject($this->subject);
                $mailer->isHTML(true);
                $mailer->Encoding = 'base64';
                $mailer->setBody($this->content);
                $user = JFactory::getUser();
                $mailer->addReplyTo($user->email,$user->name);
                
                $mail_sending_ok = true;
                if(count($multiple_mail_addresses) > 0)
                {
                  $mailer->addBCC($multiple_mail_addresses);
                
                  // print "<pre>";
                  // print_r($mailer);
                  // die();                  

                  //Send one mail with all receivers who are not GMX or WEB accounts
                  $send = $mailer->Send();
                  if ($send !== true) {
                      JError::raiseWarning(100, JText::_('MOD_KANDANDA_MASSMAIL_MAIL_FAILED'));
                      $mail_sending_ok = false;
                  }
                }
                
                //send for all mails containing GMX or WEB accounts one mail with only one recipient
                foreach ($single_mail_addresses as $addr) 
                {
                    //$mailer->ClearBCCs();
                    //$mailer->addBCC($addr);
                    $mailer->clearAllRecipients();
                    $mailer->addRecipient($addr);
                
                    $send = $mailer->Send();
                    if ($send !== true) {
                        JError::raiseWarning(100, JText::_('MOD_KANDANDA_MASSMAIL_MAIL_FAILED'));
                        $mail_sending_ok = false;
                        break;
                    }
                }
                
                //Check if all mails were correctly sent
                if($mail_sending_ok) {
                  JFactory::getApplication()->enqueueMessage(JText::_('MOD_KANDANDA_MASSMAIL_MAIL_SUCCEEDED'),'notice');
                }

                //if a notification mail address is configured in module parameters
                if ($this->params->get('notification_email', '') != '') {
                    $mailer->ClearAllRecipients();
                    $mailer->addRecipient($this->params->get('notification_email', ''));
                    $user = JFactory::getUser();
                    $body = utf8_decode(JText::sprintf('MOD_KANDANDA_MASSMAIL_MAIL_NOTIFICATION_MAIL', $user->name, $user->username, $user->email, $this->content, implode("<br/>", array_unique($recipient))));
                    $mailer->setBody($body);
                    $send = $mailer->Send();
                }
            }
        }
    }

    /**
     * Get all data out of email form and store it in class attributes
     */
    private function getFormInput() {
        // Get form input
        $input = JFactory::getApplication()->input;
        // Get the form data
        $this->form_data = new JRegistry($input->get('kandanda_massmail_form_fields', '', 'array'));

        //get all selected Kandanda groups to which members emails should be sent to
        $this->receiver_group = $this->form_data->get('receiver_group');

        //get all Kandanda select field ids which are marked in module configuration
        $select_field_ids = $this->params->get('kandanda_selects', '');
        if (is_array($select_field_ids) && count($select_field_ids) > 0) {
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);
            foreach ($select_field_ids as $value) {
                $query->clear();
                $query->select('*');
                $query->from('#__kandanda_fields');
                $query->where('id = ' . $value);
                $db->setQuery($query);
                //result holds assoc array of kandanda select field configuration data
                $result = $db->loadAssoc();

                //create assoc array which holds the Kandanda select field id and the corresponding selected elements from frontend form
                $this->receiver_select[] = array('field_id' => $value, 'selection' => $this->form_data->get('receiver_select_' . $result['alias']));
            }
        }

        //get status of checkbox "all members"
        $this->all_members = $this->form_data->get('all_members');

        //get subject for email out of form
        $this->subject = $this->form_data->get('email_subject');
        //get email content out of form
        $this->content = $this->form_data->get('email_content');
        //if php option 'magic_quotes' is active -> remove slashes from emial content
        if (get_magic_quotes_gpc()) {
            $this->content = stripslashes($this->content);
        }
    }

    /**
     * Get email addresses of Kandanda members which are assigned to the 
     * selected Kandanda groups or Kandanda Select fields
     * 
     * @return assoc array with Kandanda member data containing member id,
     * email address, lastname and firstname
     */
    private function getMailAccounts() {
        //if email should be sent to all Kandanda members
        if ($this->all_members == 1) {
            //get mail accounts from all Kandanda members
            $MailAccounts = $this->getMailAccountsFromAllMembers();
        } else {
            //get mail accounts from Kandanda members which are assigned to selected groups
            $MailAccounts = $this->getMailAccountsFromGroups();

            //get mail accounts from Kandanda members which are assigned to selected Kandanda select fields
            $MailAccountsSelect = $this->getMailAccountsFromSelect();

            //if mail accounts assigned to select fields exists -> add it to $MailAccounts array
            if (is_array($MailAccountsSelect)) {
                foreach ($MailAccountsSelect as $entry) {
                    if ($this->recursive_array_search($entry['MemberID'], $MailAccounts) === false) {
                        $MailAccounts[] = $entry;
                    }
                }
            }
        }

        //check if additional email accounts exists and add it to $MailAccounts array
        $additionalMail = explode(",", $this->form_data->get('additional_email'));
        if (is_array($additionalMail) && count($additionalMail) > 0) {
            foreach ($additionalMail as $entry) {
                if ($entry != "" && $this->recursive_array_search($entry, $MailAccounts) === false) {
                    $array = array(
                        "MemberID" => "-1",
                        "Name" => "",
                        "Vorname" => "",
                        "Email" => $entry);
                    $MailAccounts[] = $array;
                }
            }
        }

        return $MailAccounts;
    }

    /**
     * Get email addresses of Kandanda members which are assigned to the selected
     * Kandanda groups.
     */
    private function getMailAccountsFromGroups() {
        if (count((array)$this->receiver_group) > 0) {
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);
            $query->clear();

            foreach ($this->receiver_group as $value) {
                $sSQL[] = $db->quoteName('c.id') . ' = ' . $value;
            }

            //get module parameter for id of Kandanda field which contains the email adress of a member
            $email_field_id = $this->params->get('email_field', '');

            $query->select($db->quoteName(array('a.member_id', 'f.value', 'h.value', 'k.value'), array('MemberID', 'Name', 'Vorname', 'Email')));
            $query->from($db->quoteName('#__kandanda_fields_values', 'f'));
            $query->join('LEFT', $db->quoteName('#__kandanda_accounts', 'a') . ' ON (' . $db->quoteName('a.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
            //$query->join('LEFT', $db->quoteName('#__kandanda_members', 'g') . ' ON (' . $db->quoteName('g.id') . ' = ' . $db->quoteName('a.member_id') . ')');
            $query->join('LEFT', $db->quoteName('#__kandanda_member_category_map', 'm') . ' ON (' . $db->quoteName('m.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
            $query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON (' . $db->quoteName('m.catid') . ' = ' . $db->quoteName('c.id') . ' AND (' . implode(' OR ', $sSQL) . '))');
            $query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'h') . ' ON (' . $db->quoteName('h.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('h.field_id') . '= 1)'); //field id 1 is always Kandanda fixed field 'firstname'
            $query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'k') . ' ON (' . $db->quoteName('k.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('k.field_id') . '= ' . $email_field_id . ' AND ' . $db->quoteName('k.value') . ' <> \'\')');
            $query->where($db->quoteName('published') . ' = ' . $db->quote('1'));
            $query->where($db->quoteName('f.field_id') . ' = ' . $db->quote('2')); //field id 2 is always Kandanda fixed field 'lastname'
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
     */
    private function getMailAccountsFromSelect() {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $result = array();

        if (is_array($this->receiver_select)) {
            foreach ($this->receiver_select as $KandandaSelect) {
                $select_field_id = $KandandaSelect['field_id'];
                $receiver = $KandandaSelect['selection'];
                //get module parameter for id of Kandanda field which contains the email adress of a member
                $email_field_id = $this->params->get('email_field', '');

                if (count((array)$receiver) > 0) {
                    $query->clear();

                    //Values of Kandanda select fields are stored separated by linefeed('\n')
                    //So the regex should match the first entry after begin of Kandanda select field value
                    //or the first entry after a line feed character.
                    //The regex syntax is used to avoid listing entries which contains the searched value as a sub part
                    //So only entries which contains the whole search entry at the beginning of a line
                    foreach ($receiver as $value) {
                        $sSQL[] = $db->quoteName('i.value') . ' REGEXP "(^|\n)' . $value . '"';
                    }

                    $query->select($db->quoteName(array('a.member_id', 'f.value', 'h.value', 'k.value'), array('MemberID', 'Name', 'Vorname', 'Email')));
                    $query->from($db->quoteName('#__kandanda_fields_values', 'f'));
                    $query->join('LEFT', $db->quoteName('#__kandanda_accounts', 'a') . ' ON (' . $db->quoteName('a.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
                    $query->join('LEFT', $db->quoteName('#__kandanda_members', 'g') . ' ON (' . $db->quoteName('g.id') . ' = ' . $db->quoteName('a.member_id') . ')');
                    $query->join('LEFT', $db->quoteName('#__kandanda_member_category_map', 'm') . ' ON (' . $db->quoteName('m.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
                    $query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON (' . $db->quoteName('m.catid') . ' = ' . $db->quoteName('c.id') . ')');
                    $query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'h') . ' ON (' . $db->quoteName('h.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('h.field_id') . '= 1)'); //field id 1 is always Kandanda fixed field 'firstname'
                    $query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'k') . ' ON (' . $db->quoteName('k.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('k.field_id') . '= ' . $email_field_id . ' AND ' . $db->quoteName('k.value') . ' <> \'\')');
                    $query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'i') . ' ON (' . $db->quoteName('i.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('i.field_id') . '= ' . $select_field_id . ' AND (' . implode(' OR ', $sSQL) . '))');
                    $query->where($db->quoteName('g.published') . ' = ' . $db->quote('1'));
                    $query->where($db->quoteName('f.field_id') . ' = ' . $db->quote('2')); //field id 2 is always Kandanda fixed field 'lastname'
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
     */
    private function getMailAccountsFromAllMembers() {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->clear();

        //get module parameter for id of Kandanda field which contains the email adress of a member
        $email_field_id = $this->params->get('email_field', '');

        $query->select($db->quoteName(array('a.member_id', 'f.value', 'h.value', 'k.value'), array('MemberID', 'Name', 'Vorname', 'Email')));
        $query->from($db->quoteName('#__kandanda_fields_values', 'f'));
        $query->join('LEFT', $db->quoteName('#__kandanda_accounts', 'a') . ' ON (' . $db->quoteName('a.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
        $query->join('LEFT', $db->quoteName('#__kandanda_members', 'g') . ' ON (' . $db->quoteName('g.id') . ' = ' . $db->quoteName('a.member_id') . ')');
        $query->join('LEFT', $db->quoteName('#__kandanda_member_category_map', 'm') . ' ON (' . $db->quoteName('m.member_id') . ' = ' . $db->quoteName('f.member_id') . ')');
        $query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON (' . $db->quoteName('m.catid') . ' = ' . $db->quoteName('c.id') . ')');
        $query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'h') . ' ON (' . $db->quoteName('h.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('h.field_id') . '= 1)'); //field id 1 is always Kandanda fixed field 'firstname'
        $query->join('INNER', $db->quoteName('#__kandanda_fields_values', 'k') . ' ON (' . $db->quoteName('k.member_id') . ' = ' . $db->quoteName('f.member_id') . ' AND ' . $db->quoteName('k.field_id') . '= ' . $email_field_id . ' AND ' . $db->quoteName('k.value') . ' <> \'\')');
        $query->where($db->quoteName('g.published') . ' = ' . $db->quote('1'));
        $query->where($db->quoteName('f.field_id') . ' = ' . $db->quote('2')); //field id 2 is always Kandanda fixed field 'lastname'
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
     * @param type $needle
     * @param array $haystack
     * @return boolean
     */
    private function recursive_array_search($needle, $haystack) {
        if (is_array($haystack)) {
            foreach ($haystack as $key => $value) {
                $current_key = $key;
                if ($needle === $value OR ( is_array($value) && $this->recursive_array_search($needle, $value) !== false)) {
                    return $current_key;
                }
            }
        }
        return false;
    }

    private function getFile($key, $destinationFolder) {
        /**
         *  now let's process uploads: the array files contains a key "$key" which is the key name.
         *  we need to copy the files uploaded
         *  (if any are there and if they match the field filter = pdf)
         *  and set the data->pdf to its new path.
         * */
        $file = JRequest::getVar('jform', array(), 'files', 'array');
        if ($file['error'][$key] != "0") {
            error_log('no files uploaded, exiting now');
            return "";
        }

        //error_log('OFFER FOUND FILES '.var_export($file,true));
        $tempName = $file['tmp_name'][$key];
        $tempFullPath = ini_get('upload_tmp_dir') . $tempName;
        $type = $file['type'][$key];
        $name = $file['name'][$key];
        //error_log('DATA FOUND: '. "temp: $tempName , type: $type, name: $name");
        if (file_exists($tempFullPath)) {
            if (mkdir(JPATH_SITE . $destinationFolder, 0755, true)) {
                if (copy($source = $tempFullPath, $dest = JPATH_SITE . $destinationFolder . "/" . $name)) {
                    return $destinationFolder . "/" . $name;
                } else {
                    error_log('could not copy ' . "$source to $dest");
                }
            } else {
                error_log('could not create folder ' . JPATH_SITE . $destinationFolder);
            }
            return "";
        } else {
            error_log('FILE NOT FOUND: ' . $tempFullPath);
        }
    }

}
