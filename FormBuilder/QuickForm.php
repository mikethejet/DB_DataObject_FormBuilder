<?php
/**
 * This is a driver class for the DB_DataObject_FormBuilder package.
 * It uses HTML_QuickForm to render the forms.
 *
 * PHP Versions 4 and 5
 *
 * @category DB
 * @package  DB_DataObject_FormBuilder
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @author   Markus Wolff <mw21st@php.net>
 * @author   Justin Patrin <papercrane@reversefold.com>
 * @version  $Id$
 */

require_once ('HTML/QuickForm.php');

/**
 * This is a driver class for the DB_DataObject_FormBuilder package.
 * It uses HTML_QuickForm to render the forms.
 */
class DB_DataObject_FormBuilder_QuickForm
{
    /**
     * Array to determine what QuickForm element types are being used for which
     * general field types. If you configure FormBuilder using arrays, the format is:
     * array('nameOfFieldType' => 'QuickForm_Element_name', ...);
     * If configured via .ini file, the format looks like this:
     * elementTypeMap = shorttext:text,date:date,...
     *
     * Allowed field types:
     * <ul><li>shorttext</li>
     * <li>longtext</<li>
     * <li>date</li>
     * <li>integer</li>
     * <li>float</li></ul>
     */
    var $elementTypeMap = array('shorttext' => 'text',
                                'longtext'  => 'textarea',
                                'date'      => 'date',
                                'time'      => 'date',
                                'datetime'  => 'date',
                                'integer'   => 'text',
                                'float'     => 'text',
                                'select'    => 'select',
                                'multiselect'  => 'select',
                                'subForm'      => 'subFormFB',
                                'elementTable' => 'elementTable');

    /**
     * Array of attributes for each element type. See the keys of elementTypeMap
     * for the allowed element types.
     *
     * The key is the element type. The value can be a valid attribute string or
     * an associative array of attributes.
     */
    var $elementTypeAttributes = array();

    /**
     * Array of attributes for each specific field.
     *
     * The key is the field name. The value can be a valid attribute string or
     * an associative array of attributes.
     */
    var $fieldAttributes = array();

    /**
     * The following member variables are set to force copying from the FormBuilder object
     */
    var $formHeaderText;
    var $linkNewValue;
    var $linkNewValueText;
    var $elementNamePrefix;
    var $elementNamePostfix;
    var $dateElementFormat;
    var $dateFieldLanguage;
    var $timeElementFormat;
    var $dateTimeElementFormat;
    var $requiredRuleMessage;
    var $clientRules;
    var $dateOptionsCallback;
    var $timeOptionsCallback;
    var $dateTimeOptionsCallback;

    /**
     * Holds the QuickForm object
     */
    var $_form;

    /**
     * Holds a QuickForm object to append to the created form
     */
    var $_appendForm;

    /**
     * The FormBuilder object this driver is attached to
     */
    var $_fb;

    /**
     * DB_DataObject_FormBuilder_QuickForm::DB_DataObject_FormBuilder_QuickForm()
     *
     * The class constructor.
     *
     * @param DB_DataObject_FormBuilder $fb the FormBuilder object this driver is attached to
     * @access public
     */
    function DB_DataObject_FormBuilder_QuickForm(&$fb)
    {
        $this->_fb =& $fb;
    }

    /**
     * Populate options from the main formbuilder class
     */
    function populateOptions() {
        foreach (get_object_vars($this) as $var => $value) {
            if ($var[0] != '_' && isset($this->_fb->$var)) {
                $this->$var = $this->_fb->$var;
            }
        }
    }
    
    /**
     * DB_DataObject_FormBuilder::useForm()
     *
     * Sometimes, it might come in handy not just to create a new QuickForm object,
     * but to work with an existing one. Using FormBuilder together with
     * HTML_QuickForm_Controller or HTML_QuickForm_Page is such an example ;-)
     * If you do not call this method before the form is generated, a new QuickForm
     * object will be created (default behaviour).
     *
     * @param $form     object  A HTML_QuickForm object (or extended from that)
     * @param $append   boolean If TRUE, the form will be appended to the one generated by FormBuilder. If false, FormBuilder will just add its own elements to this form. 
     * @return boolean  Returns false if the passed object was not a HTML_QuickForm object or a QuickForm object was already created
     * @access public
     */
    function useForm(&$form, $append = false)
    {
        if (is_a($form, 'html_quickform') && !is_object($this->_form)) {
            if ($append) {
                $this->_appendForm =& $form;
            } else {
                $this->_form =& $form;
            }
            return true;
        }
        return false;
    }

    /**
     * DB_DataObject_FormBuilder_QuickForm::_getQFType()
     *
     * Returns the QuickForm element type associated with the given field type,
     * as defined in the elementTypeMap property. If an unknown field type is given,
     * the returned type name will default to 'text'.
     *
     * @access protected
     * @param  string $fieldType   The internal field type
     * @return string              The QuickForm element type name
     */
    function _getQFType($fieldType)
    {
        if (isset($this->elementTypeMap[$fieldType])) {
            return $this->elementTypeMap[$fieldType];
        } else {
            $default = get_class_vars(get_class($this));
            if (isset($default['elementTypeMap'][$fieldType])) {
                return $default['elementTypeMap'][$fieldType];
            }
            return 'text';
        }
    }

    /**
     * DB_DataObject_FormBuilder_QuickForm::_getAttributes()
     *
     * Returns the attributes to apply to a field based on the field name and
     * element type. The field's attributes take precedence over the element type's.
     *
     * @param string $elementType the internal type of the element
     * @param string $fieldName the name of the field
     * @return array an array of attributes to apply to the element
     */
    function _getAttributes($elementType, $fieldName) {
        if (isset($this->elementTypeAttributes[$elementType])) {
            if (is_string($this->elementTypeAttributes[$elementType])) {
                $this->elementTypeAttributes[$elementType] =
                    HTML_Common::_parseAttributes($this->elementTypeAttributes[$elementType]);
            }
            $attr = $this->elementTypeAttributes[$elementType];
        } else {
            $attr = array();
        }
        if (isset($this->fieldAttributes[$fieldName])) {
            if (is_string($this->fieldAttributes[$fieldName])) {
                $this->fieldAttributes[$fieldName] =
                    HTML_Common::_parseAttributes($this->fieldAttributes[$fieldName]);
            }
            $attr = array_merge($attr, $this->fieldAttributes[$fieldName]);
        }
        return $attr;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createFormObject()
     *
     * Creates a QuickForm object to be used by _generateForm().
     *
     * @param string $formName  The name of the form
     * @param string $method    Method for transferring form data over HTTP (GET|POST)
     * @param string $action    The script to transfer the form data to
     * @param string $target    Name of the target frame/window to use to display the "action" script
     * @return object           The HTML_QuickForm object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function _createFormObject($formName, $method, $action, $target)
    {
        if (!is_a($this->_form, 'html_quickform')) {
            $this->_form =& new HTML_QuickForm($formName, $method, $action, $target, null, true);
        }
    }

    /**
     * Get the name of a form element
     *
     * @param HTML_QuickForm_element the element to get the name of
     * @return string the name of the element
     */
    function _getElementName(&$element) {
        return $element->getName();
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_addFormHeader()
     *
     * Adds a header to the given form. Will use the header defined in the "formHeaderText" property.
     * Used in _generateForm().
     *
     * @param string $text THe text for the header
     *
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function _addFormHeader($text)
    {
        $this->_form->addElement('header', '', $text);
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createHiddenField()
     *
     * Returns a QuickForm element for a hidden field.
     * Used in _generateForm().
     *
     * @param string $fieldName    The field name to use for the QuickForm element
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createHiddenField($fieldName)
    {
        $element =& HTML_QuickForm::createElement('hidden',
                                                  $this->_fb->getFieldName($fieldName));   
        $attr = $this->_getAttributes('hidden', $fieldName);
        $element->updateAttributes($attr);
        return $element;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createRadioButtons()
     *
     * Returns a QuickForm element for a group of radio buttons.
     * Used in _generateForm().
     *
     * @param string $fieldName    The field name to use for the QuickForm element group
     * @param array  $options      The list of options to generate the radio buttons for
     * @return array               Array of HTML_QuickForm_element objects.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createRadioButtons($fieldName, $options)
    {
        $element = array();
        $attr = $this->_getAttributes('radio', $fieldName);
        foreach($options as $value => $display) {
            unset($radio);
            $radio =& HTML_QuickForm::createElement('radio',
                                                    $this->_fb->getFieldName($fieldName),
                                                    null, 
                                                    $display,
                                                    $value);
            $radio->updateAttributes($attr);
            $element[] =& $radio;
        }
        return $element;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createCheckbox()
     *
     * Returns a QuickForm element for a checkbox.
     * Used in _generateForm().
     *
     * @param string $fieldName    The field name to use for the QuickForm element
     * @param string $text         Text to label the checkbox
     * @param string $value        The value that is submitted when the checkbox is checked
     * @param boolean $checked     Is the checkbox checked? (Default: False)
     * @param boolean $freeze      Is the checkbox frozen? (Default: False)
     * @return object              The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createCheckbox($fieldName, $text = null, $value = null, $label = null, $checked = false, $freeze = false)
    {
        $element =& HTML_QuickForm::createElement('checkbox',
                                                  $this->_fb->getFieldName($fieldName),
                                                  $label,
                                                  $text);
        if ($value !== null) {
            $element->updateAttributes(array('value' => $value));
        }
        if ($checked) {
            $element->setChecked(true);
        }
        if ($freeze) {
            $element->freeze();
        }
        $attr = $this->_getAttributes('checkbox', $fieldName);
        $element->updateAttributes($attr);
        return $element;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createTextField()
     *
     * Returns a QuickForm element for a single-line text field.
     * Used in _generateForm().
     *
     * @param string $fieldName    The field name to use for the QuickForm element
     * @return object              The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createTextField($fieldName)
    {
        $element =& HTML_QuickForm::createElement($this->_getQFType('shorttext'),
                                                  $this->_fb->getFieldName($fieldName),
                                                  $this->_fb->getFieldLabel($fieldName));
        $attr = $this->_getAttributes('shorttext', $fieldName);
        $element->updateAttributes($attr);
        return $element;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createIntegerField()
     *
     * Returns a QuickForm element for an integer field.
     * Used in _generateForm().
     *
     * @param string $fieldName    The field name to use for the QuickForm element
     * @return object              The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createIntegerField($fieldName)
    {
        $element =& HTML_QuickForm::createElement($this->_getQFType('integer'),
                                                  $this->_fb->getFieldName($fieldName),
                                                  $this->_fb->getFieldLabel($fieldName));
        $attr = $this->_getAttributes('integer', $fieldName);
        $element->updateAttributes($attr);
        return $element;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createTextArea()
     *
     * Returns a QuickForm element for a long text field.
     * Used in _generateForm().
     *
     * @param string $fieldName    The field name to use for the QuickForm element
     * @return object              The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createTextArea($fieldName)
    {
        $element =& HTML_QuickForm::createElement($this->_getQFType('longtext'),
                                                  $this->_fb->getFieldName($fieldName),
                                                  $this->_fb->getFieldLabel($fieldName));
        $attr = $this->_getAttributes('longtext', $fieldName);
        $element->updateAttributes($attr);
        return $element;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createSelectBox()
     *
     * Returns a QuickForm element for a selectbox/combobox.
     * Used in _generateForm().
     *
     * @param string  $fieldName   The field name to use for the QuickForm element
     * @param array   $options     List of options for populating the selectbox
     * @param boolean $multiple    If set to true, the select box will be a multi-select
     * @return object              The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createSelectBox($fieldName, $options, $multiple = false)
    {
        if ($multiple) {
            $element =& HTML_QuickForm::createElement($this->_getQFType('multiselect'),
                                                      $this->_fb->getFieldName($fieldName),
                                                      $this->_fb->getFieldLabel($fieldName),
                                                      $options,
                                                      array('multiple' => 'multiple'));
        } else {
            $element =& HTML_QuickForm::createElement($this->_getQFType('select'),
                                                      $this->_fb->getFieldName($fieldName),
                                                      $this->_fb->getFieldLabel($fieldName),
                                                      $options);
            $attr = $this->_getAttributes('select', $fieldName);
            $element->updateAttributes($attr);
            if (isset($this->linkNewValue[$fieldName])) {
                $links = $this->_fb->_do->links();
                if (isset($links[$fieldName])) {
                    list($table,) = explode(':', $links[$fieldName]);
                    require_once('DB/DataObject/FormBuilder/QuickForm/SubFormFB.php');
                    $element->addOption($this->linkNewValueText, $this->linkNewValueText);
                    $element->updateAttributes(array('onchange' => 'db_do_fb_'.$this->_fb->getFieldName($fieldName).'__subForm_display(this)'));
                    $element->updateAttributes(array('id' => $element->getName()));
                    $this->_prepareForLinkNewValue($fieldName, $table);
                    $subFormElement =& HTML_QuickForm::createElement($this->_getQFType('subForm'),
                                                                     $this->_fb->getFieldName($fieldName).'__subForm',
                                                                     '',
                                                                     $this->_linkNewValueForms[$fieldName]);
                    $subFormElement->setPreValidationCallback(array(&$subFormElement, 'preValidationCallback'));
                    $subFormElement->linkNewValueText = $this->linkNewValueText;
                    $subFormElement->selectName = $this->_fb->getFieldName($fieldName);
                    $el =& $this->_form->addElement('hidden', $this->_fb->getFieldName($fieldName).'__subForm__displayed');
                    $el->updateAttributes(array('id' => $el->getName()));
                    $element =& HTML_QuickForm::createElement('group',
                                                              $this->_fb->getFieldName($fieldName),
                                                              $this->_fb->getFieldLabel($fieldName),
                                                              array($element, $subFormElement),
                                                              '<br/>',
                                                              false);
                }
            }
        }
        return $element;
    }

    /**
     * Adds a form rule for linkNew entries
     *
     * @param HTML_QuickForm the form to add the rule to
     */
    function _addRuleForLinkNewValues() {
        $this->_form->addFormRule(array(&$this, 'validateLinkNewValues'));
    }

    /**
     * Loops through linkNewValue forms and makes sure that the submitted values are valid
     *
     * @param  Array the array of posted values
     * @return mixed true if everything is valid, else an array with QF rule messages
     */
    function validateLinkNewValues($values) {
        $valid = true;
        /*if (isset($values[$this->elementNamePrefix.'__DB_DataObject_FormBuilder_linkNewValue_'.$this->elementNamePostfix])) {
            foreach ($values[$this->elementNamePrefix.'__DB_DataObject_FormBuilder_linkNewValue_'.$this->elementNamePostfix] as $elName => $subTable) {*/
        if (isset($this->_linkNewValueForms)) {
            foreach (array_keys($this->_linkNewValueForms) as $elName) {
                $subTable = $this->_linkNewValueDOs[$elName]->tableName();
                if (isset($values[$this->elementNamePrefix.'__DB_DataObject_FormBuilder_linkNewValue_'.$this->elementNamePostfix.'__'.$elName])) {
                    if ($values[$this->elementNamePrefix.$elName.$this->elementNamePostfix] == '--New Value--') {
                        $this->_prepareForLinkNewValue($elName, $subTable);
                        if (!$this->_linkNewValueForms[$elName]->validate()) {
                            $valid = false;
                        }
                    }
                }
            }
        }
        if ($valid) {
            return true;
        } else {
            return array($elName => 'Please fix the errors below');
        }
    }

    /**
     * Populates internal vars for linkNewValue
     *
     * @param  string the name of the element we're creating the form for
     * @param  string the name of the table to create the form for (linked table)
     */
    function _prepareForLinkNewValue($elName, $subTable) {
        if (!isset($this->_linkNewValueDOs[$elName])) {
            $this->_linkNewValueDOs[$elName] =& DB_DataObject::factory($subTable);
            $this->_linkNewValueDOs[$elName]->fb_createSubmit = false;
            $this->_linkNewValueDOs[$elName]->fb_elementNamePrefix = $this->elementNamePrefix.$elName.'_'.$subTable.'__';
            $this->_linkNewValueDOs[$elName]->fb_elementNamePostfix = $this->elementNamePostfix;
            $this->_linkNewValueDOs[$elName]->fb_linkNewValue = false;
            $this->_linkNewValueFBs[$elName] =& DB_DataObject_FormBuilder::create($this->_linkNewValueDOs[$elName]);
            $this->_linkNewValueForms[$elName] =& $this->_linkNewValueFBs[$elName]->getForm();
            $this->_linkNewValueForms[$elName]->addElement('hidden',
                                                           $this->elementNamePrefix.'__DB_DataObject_FormBuilder_linkNewValue_'.
                                                           $this->elementNamePostfix.'__'.$elName, $subTable);
        }
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createStaticField()
     *
     * Returns a QuickForm element for displaying static HTML.
     * Used in _generateForm().
     *
     * @param string $fieldName    The field name to use for the QuickForm element
     * @param string $text         The text or HTML code to display in place of this element
     * @return object              The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createStaticField($fieldName, $text = null)
    {
        $element =& HTML_QuickForm::createElement('static',
                                                  $this->_fb->getFieldName($fieldName),
                                                  $this->_fb->getFieldLabel($fieldName),
                                                  $text);
        $attr = $this->_getAttributes('static', $fieldName);
        $element->updateAttributes($attr);
        return $element;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_addElementGroup()
     *
     * Adds a group of elements to a form object
     * Used in _generateForm().
     *
     * @param array  $element      Array of QuickForm element objects
     * @param string $fieldName    The field name to use for the QuickForm element group
     * @param string $separator    Some text or HTML snippet used to separate the group entries
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function _addElementGroup(&$element, $fieldName, $separator = '')
    {
        $this->_form->addGroup($element,
                               $this->_fb->getFieldName($fieldName),
                               $this->_fb->getFieldLabel($fieldName),
                               $separator,
                               false);
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_addElement()
     *
     * Adds a QuickForm element to a form object
     * Used in _generateForm().
     *
     * @param object $element The element object to be added
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function _addElement(&$element)
    {
        $this->_form->addElement($element);   
    }

    /**
     * DB_DataObject_FormBuilder_QuickForm::_addSubmitButton()
     *
     * @param string the name of the submit element to be created
     * @param string the text to be put on the submit button
     */
    function _addSubmitButton($fieldName, $text)
    {
        $element =& $this->_createSubmitButton($fieldName, $text);
        $this->_addElement($element);
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createSubmitButton()
     *
     * Returns a QuickForm element for a submit button.
     * Used in _generateForm().
     *
     * @param  string      the name of the submit button
     * @param  string      the text to put in the button
     * @return object      The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createSubmitButton($fieldName, $text)
    {
        $element =& HTML_QuickForm::createElement('submit', $fieldName, $text);
        $attr = $this->_getAttributes('submit', $fieldName);
        $element->updateAttributes($attr);
        return $element;
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createDateElement()
     *
     * Returns a QuickForm element for entering date values.
     * Used in _generateForm().
     *
     * @param string $fieldName  The field name to use for the element
     * @return object       The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createDateElement($fieldName) {
        $dateOptions = array('format' => $this->dateElementFormat,
                             'language' => $this->dateFieldLanguage);
        if (is_callable($this->dateOptionsCallback)) {
            $dateOptions = array_merge($dateOptions,
                                       call_user_func_array($this->dateOptionsCallback,
                                                            array($fieldName, &$fb)));
        }
        if (!isset($dateOptions['addEmptyOption']) && in_array($fieldName, $this->_fb->selectAddEmpty)) {
            $dateOptions['addEmptyOption'] = true;
            $dateOptions['emptyOptionText'] = $this->_fb->selectAddEmptyLabel;
        }
        $element =& HTML_QuickForm::createElement($this->_getQFType('date'),
                                                  $this->_fb->getFieldName($fieldName),
                                                  $this->_fb->getFieldLabel($fieldName),
                                                  $dateOptions);
        $attr = $this->_getAttributes('date', $fieldName);
        $element->updateAttributes($attr);
        return $element;  
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_createTimeElement()
     *
     * Returns a QuickForm element for entering time values.
     * Used in _generateForm().
     * Note by Frank: The only reason for this is the difference in timeoptions so it 
     * probably would be better integrated with _createDateElement
     *
     * @param string $fieldName The field name to use for the element
     * @return object      The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createTimeElement($fieldName) {
        $timeOptions = array('format' => $this->timeElementFormat,
                             'language' => $this->dateFieldLanguage);
        if (is_callable($this->timeOptionsCallback)) {
            $timeOptions = array_merge($timeOptions,
                                       call_user_func_array($this->timeOptionsCallback,
                                                            array($fieldName, &$fb)));
        }
        if (!isset($timeOptions['addEmptyOption']) && in_array($fieldName, $this->_fb->selectAddEmpty)) {
            $timeOptions['addEmptyOption'] = true;
            $timeOptions['emptyOptionText'] = $this->_fb->selectAddEmptyLabel;
        }
        $element =& HTML_QuickForm::createElement($this->_getQFType('time'),
                                                  $this->_fb->getFieldName($fieldName),
                                                  $this->_fb->getFieldLabel($fieldName),
                                                  $timeOptions);
        $attr = $this->_getAttributes('time', $fieldName);
        $element->updateAttributes($attr);
        return $element;  
    }

    /**
     * DB_DataObject_FormBuilder_QuickForm::_createDateTimeElement()
     *
     * Returns a QuickForm element for entering date values.
     * Used in _generateForm().
     *
     * @param string $fieldName  The field name to use for the element
     * @return object       The HTML_QuickForm_element object.
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function &_createDateTimeElement($fieldName) {
        $dateOptions = array('format' => $this->dateTimeElementFormat,
                             'language' => $this->dateFieldLanguage);
        if (is_callable($this->dateTimeOptionsCallback)) {
            $dateOptions = array_merge($dateOptions,
                                       call_user_func_array($this->dateTimeOptionsCallback,
                                                            array($fieldName, &$fb)));
        }
        if (!isset($dateOptions['addEmptyOption']) && in_array($fieldName, $this->_fb->selectAddEmpty)) {
            $dateOptions['addEmptyOption'] = true;
            $dateOptions['emptyOptionText'] = $this->_fb->selectAddEmptyLabel;
        }
        $element =& HTML_QuickForm::createElement($this->_getQFType('datetime'),
                                                  $this->_fb->getFieldName($fieldName),
                                                  $this->_fb->getFieldLabel($fieldName),
                                                  $dateOptions);
        $attr = $this->_getAttributes('datetime', $fieldName);
        $element->updateAttributes($attr);
        return $element;  
    }

    /**
     * DB_DataObject_FormBuilder_QuickForm::_addElementTable
     *
     * Adds an elementTable to the form
     *
     * @param string         $fieldName        the name of the element to be added
     * @param array          $columnNames an array of the column names
     * @param array          $rowNames    an array of the row names
     * @param array          $rows        an array of rows, each row being an array of HTML_QuickForm elements
     */
    function _addElementTable($fieldName, $columnNames, $rowNames, &$rows) {
        if (!HTML_QuickForm::isTypeRegistered('elementTable')) {
            HTML_QuickForm::registerElementType('elementTable',
                                                'DB/DataObject/FormBuilder/QuickForm/ElementTable.php',
                                                'DB_DataObject_FormBuilder_QuickForm_ElementTable');
        }
        $element =& HTML_QuickForm::createElement($this->_getQFType('elementTable'),
                                                  $this->_fb->getFieldName($fieldName),
                                                  $this->_fb->getFieldLabel($fieldName));
        $element->setColumnNames($columnNames);
        $element->setRowNames($rowNames);
        $element->setRows($rows);
        $attr = $this->_getAttributes('elementTable', $fieldName);
        $element->updateAttributes($attr);
        $this->_addElement($element);
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_setFormDefaults()
     *
     * @param HTML_QuickForm the form to set the defaults on
     * @param array Assoc array of default values (@see HTML_QuickForm::setDefaults)
     */    
    function _setFormDefaults($defaults)
    {
        $this->_form->setDefaults($defaults);
    }

    /**
     * DB_DataObject_FormBuilder_QuickForm::_setFormElementRequired()
     *
     * Adds a required rule for a specific element to a form
     * Used in _generateForm().
     *
     * @param object $fieldName The name of the required field
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function _setFormElementRequired($fieldName)
    {
        $this->_addFieldRules(array(array('validator' => 'required',
                                          'rule' => false,
                                          'message' => $this->requiredRuleMessage)),
                              $fieldName);
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_addFieldRules()
     *
     * Adds a set of rules to a form that will apply to a specific element
     * Used in _generateForm().
     *
     * @param array  $rules     Array of rule names to be enforced on the element (must be registered QuickForm rules)
     * @param string $fieldName Name of the form element in question
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function _addFieldRules($rules, $fieldName)
    {
        $fieldLabel = $this->_fb->getFieldLabel($fieldName);
        $ruleSide = $this->clientRules ? 'client' : 'server';
        foreach ($rules as $rule) {
            $realFieldName = $this->_fb->getFieldName($fieldName);
            $el =& $this->_form->getElement($realFieldName);
            if (is_a($el, 'HTML_QuickForm_Date')) {
                $ruleFunction = 'addGroupRule';
            } else {
                $ruleFunction = 'addRule';
            }
            if ($rule['rule'] === false) {
                $this->_form->$ruleFunction($realFieldName,
                                            sprintf($rule['message'], $fieldLabel),
                                            $rule['validator'],
                                            '', 
                                            $ruleSide);
            } else {
                $this->_form->$ruleFunction($realFieldName,
                                            sprintf($rule['message'], $fieldLabel),
                                            $rule['validator'],
                                            $rule['rule'],
                                            $ruleSide);
            } // End if
        } // End while
    }
    
    /**
     * DB_DataObject_FormBuilder_QuickForm::_freezeFormElements()
     *
     * Freezes a list of form elements (set read-only).
     * Used in _generateForm().
     *
     * @param array  $elements_to_freeze List of element names to be frozen
     * @access protected
     * @see DB_DataObject_FormBuilder::_generateForm()
     */
    function _freezeFormElements($elementsToFreeze)
    {
        foreach ($elementsToFreeze as $elementToFreeze) {
            $elementToFreeze = $this->_fb->getFieldName($elementToFreeze);
            if ($this->_form->elementExists($elementToFreeze)) {
                $el =& $this->_form->getElement($elementToFreeze);
                $el->freeze();
            }
        }   
    }

    /**
     * Moves an element before another
     *
     * @param string the name of the element to move
     * @param string the name of the element to move the first before
     */
    function _moveElementBefore($el, $beforeEl) {
        $el = $this->_fb->getFieldName($el);
        $beforeEl = $this->_fb->getFieldName($beforeEl);
        if ($this->_form->elementExists($beforeEl) && $this->_form->elementExists($el)) {
            $this->_form->insertElementBefore($this->_form->removeElement($el), $beforeEl);
        }
    }

    /**
     * Called by the main FormBuilder class when the form is finished
     */
    function _finishForm() {
        //APPEND EXISTING FORM ELEMENTS
        if (is_a($this->_appendForm, 'html_quickform')) {
            // There somehow needs to be a new method in QuickForm that allows to fetch
            // a list of all element names currently registered in a form. Otherwise, there
            // will be need for some really nasty workarounds once QuickForm adopts PHP5's
            // new encapsulation features.
            reset($this->_appendForm->_elements);
            while (list($elNum, $element) = each($this->_appendForm->_elements)) {
                $this->_addElement($element);
            }
            $form->_errors = array_merge($form->_errors, $this->_appendForm->_errors);
            $form->_rules = array_merge($form->_rules, $this->_appendForm->_rules);
            $form->_required = array_merge($form->_required, $this->_appendForm->_required);
        }
    }

    /**
     * Returns the HTML_QuickForm object that has been created
     */
    function &getForm() {
        return $this->_form;
    }

    /**
     * Adds a static element to the form which holds JavaScript and a link which
     * collpases the record table, hiding all non-selected records.
     *
     * @param string $name the name of the record list to be collapsed
     */
    function _collapseRecordList($name) {
        static $outputJs = true;
        if ($outputJs) {
            $outputJs = false;
            $js = '
<script language="javascript" type="text/javascript">
function hideRecordListRows(name) {
  checks = document.getElementsByTagName("input");
  hide = -1;
  for (i = 0; i < checks.length; ++i) {
    if (checks[i].type == "checkbox") {
      if (checks[i].name.substr(0, name.length) == name) {
        if (!checks[i].checked) {
          node = checks[i];
          while (node && node.nodeName != "TR") {
            node = node.parentNode;
          }
          if (node) {
            if (hide == -1) {
              hide = (node.style.visibility != "hidden");
            }
            if (hide) {
              node.style.visibility = "hidden";
              node.style.display = "none";
            } else {
              node.style.visibility = "";
              node.style.display = "";
            }
          }
        }
      }
    }
  }
  linkText = document.getElementById(name+"__showLink");
  if (hide) {
    linkText.innerHTML = "Show All";
  } else {
    linkText.innerHTML = "Hide Unselected";
  }
}
</script>
';
        } else {
            $js = '';
        }
        $el =& $this->_form->getElement($this->_fb->getFieldName($name));
        $el->setLabel($el->getLabel().'<br/>
<small>
<a href="javascript:hideRecordListRows(\''.htmlentities($this->_fb->getFieldName($name), ENT_QUOTES).'\');">
  <span id="'.htmlentities($this->_fb->getFieldName($name), ENT_QUOTES).'__showLink">Show All</span>
</a>
</small>'.$js);
        $this->_form->addElement('html', '
<script type="text/javascript" language="javascript">
hideRecordListRows("'.htmlentities($this->_fb->getFieldName($name), ENT_QUOTES).'");
</script>');
    }
}

?>