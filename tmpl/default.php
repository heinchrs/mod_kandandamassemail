<?php

/**
 * @package    KandandaMassEmail
 * @author     Heinl Christian <heinchrs@gmail.com>
 * @copyright  (C) 2015-2020 Heinl Christian
 * @license    GNU General Public License version 2 or later
 */

// -- No direct access
defined('_JEXEC') or die;

$document = JFactory::getDocument();
$document->addStyleSheet(JURI::root(true) . 'modules/' . $module->module . '/tmpl/css/' . $module->module . '.css');
?>


<form method="post" enctype="multipart/form-data" name="adminForm" id="adminForm">
	<div id="kandanda_massmail_container">
		<?php generateForm($params); ?>
		<div id="kandanda_massmail_submit_button_align">
			<input type="submit" name="submit_button" id="kandanda_massmail_submit_button" value="<?php echo JText::_('MOD_KANDANDA_MASSMAIL_SUBMIT_BUTTON') ?>" />
		</div>
	</div>
</form>


<?php

/**
 * Generates the input form for setting email subject, content and receivers.
 * Therefore the form XML file is read and afterwards dynamically extended
 * depending on the addtional configured email receiver groups.
 *
 * @param   array $params Module configuration parameter
 * @return  void
 */
function generateForm($params)
{
	// Create filename of form data to load
	$path = dirname(__FILE__) . DS . 'form.xml';

	// Load XML content of file, so additional form data can be added here
	$formXml = new SimpleXMLElement(file_get_contents($path));

	// Finds the fieldset with name=receiverfieldset in form xml data
	$sourcesXml = $formXml->xpath('//fieldset[@name="receiverfieldset"]');

	$db = JFactory::getDbo();
	$query = $db->getQuery(true);

	// **************************************************************************
	// * Get values for frontend listbox holding Kandanda member groups
	// **************************************************************************/
	// get module parameter which Kandanda groups should be shown for selecting email receivers
	$select_group_ids = $params->get('kandanda_groups', '');

	if (is_array($select_group_ids))
	{
		$query->clear();
		$query->select('id,title,note');
		$query->from('#__categories');
		$query->where('id IN (' . implode(",", $select_group_ids) . ')');
		$db->setQuery($query);

		// $kandanda_groups holds assoc arrays of kandanda groups
		$kandanda_groups = $db->loadAssocList();

		// Add a new field which contains the options out of Kandanda select field
		$element = $sourcesXml[0]->addChild('field', new SimpleXMLElement('<field />'));
		$element->addAttribute('type', 'list');
		$element->addAttribute('name', 'receiver_group');
		$element->addAttribute('label', 'MOD_KANDANDA_MASSMAIL_RECEIVER_GROUP_LABEL');
		$element->addAttribute('description', 'MOD_KANDANDA_MASSMAIL_RECEIVER_GROUP_DESCRIPTION');
		$element->addAttribute('default', '');
		$element->addAttribute('multiple', 'true');
		$element->addAttribute('size', count($kandanda_groups));

		foreach ($kandanda_groups as $value)
		{
			$child = $element->addChild('option', htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8'));
			$child->addAttribute('value', $value['id']);
			$child->addAttribute('title', $value['note']);
		}
	}

	// **************************************************************************
	// Get values for frontend listbox holding values of configured Kandanda select fields
	// **************************************************************************
	// Get module parameter for id of Kandanda select field which content should be shown for selecting email receivers
	$select_field_ids = $params->get('kandanda_selects', '');

	if (is_array($select_field_ids))
	{
		foreach ($select_field_ids as $value)
		{
			$query->clear();
			$query->select('*');
			$query->from('#__kandanda_fields');
			$query->where('id = ' . $value); // Funktions Feld
			$db->setQuery($query);

			// Result holds assoc array of kandanda field content
			$result = $db->loadAssoc();

			// Decode 'options' entry of kandanda field content which is in json format
			// $kandanda_select contains an assoc array for generating a list box
			$kandanda_select = json_decode($result['options'], true);

			// Add a new field which contains the options out of Kandanda select field
			$element = $sourcesXml[0]->addChild('field', new SimpleXMLElement('<field />'));
			$element->addAttribute('type', 'list');
			$element->addAttribute('name', 'receiver_select_' . $result['alias']);
			$element->addAttribute('label', $result['title']);
			$element->addAttribute('description', 'MOD_KANDANDA_MASSMAIL_RECEIVER_SELECT_DESCRIPTION');
			$element->addAttribute('default', '');
			$element->addAttribute('multiple', 'true');
			$element->addAttribute('size', count($kandanda_select['options']));

			foreach ($kandanda_select['options'] as $elementvalue)
			{
				$child = $element->addChild('option', htmlspecialchars($elementvalue, ENT_COMPAT, 'UTF-8'));
				$child->addAttribute('value', $elementvalue);
			}
		}
	}

	// **************************************************************************
	// Create form out of XML input
	// **************************************************************************
	// Load the form
	$form = new JForm('KandandaMassmailForm'); // Create the form object to hold it

	// Load the XML into the form.
	$form->load($formXml);

	// Iterate through the form fieldsets and display each one.
	foreach ($form->getFieldsets('kandanda_massmail_form_fields') as $fieldsets => $fieldset)
	{
		if (count($form->getFieldset($fieldset->name)) == 0)
		{
			continue;
		}
		?>
		<fieldset class="kandanda_massmail">
			<legend>
				<?php echo JText::_($fieldset->name . '_jform_fieldset_label'); ?>
			</legend>
			<dl>
				<?php
				// Iterate through the fields and display them.
				foreach ($form->getFieldset($fieldset->name) as $field)
				{
					// If the field is hidden, only use the input.
					if ($field->hidden)
					{
						echo $field->input;
					}
					else
					{
						?>
						<dt>
							<?php echo $field->label; ?>
						</dt>
						<dd<?php echo ($field->type == 'Editor' || $field->type == 'Textarea') ? ' style="clear: both; margin: 0;"' : '' ?>>
							<?php echo $field->input ?>
						</dd>
						<?php
					}
				}
				?>
			</dl>
		</fieldset>
		<?php
	}
}
