<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author:  Markus Wolff <mw21st@php.net>                               |
// +----------------------------------------------------------------------+

/**
 * This class adds some nice utility methods to the DataObject class
 * to speed up prototyping new applications - like auto-generating fully
 * functional forms using HTML_QuickForm.
 * 
 * The following new options to the DataObject.ini file can be used to configure
 * the form-generating behaviour of this class:
 * <ul><li>select_display_field:
 * The field to be used for displaying the options of an auto-generated
 * select element. Can be overridden individually by a similarly-named
 * public class property.</li>
 * <li>select_order_field:
 * The field to be used for sorting the options of an auto-generated
 * select element. Can be overridden individually by a similarly-named
 * public class property.</li>
 * <li>db_date_format:
 * This is for the future support of string date formats other than ISO, but
 * currently, that�s the only supported one. Set to 1 for ISO, other values
 * may be available later on.</li>
 * <li>date_element_format:
 * A format string that represents the display settings for QuickForm date elements.
 * Example: "d-M-Y". See QuickForm documentation for details on format strings.</li></ul>
 *
 * There are some more settings that can be set individually by altering
 * some special properties of your DataObject-derived classes.
 * These special properties are as follows:
 * <ul><li>preDefElements:
 * Array of user-defined QuickForm elements that will be used
 * for the field matching the array key. If no match is found,
 * the element for that field will be auto-generated.
 * Make your element objects either in the constructor or in
 * the getForm() method, before the _generateForm() method is
 * called. Use HTML_QuickForm::createElement() to do this.</li>
 * <li>fieldLabels:
 * Array of field labels. The key of the element represents the field name.
 * Use this if you want to keep the auto-generated elements, but still define
 * your own labels for them.</li>
 * <li>dateFields:
 * A simple array of field names indicating which of the fields in a particular table/class
 * are actually to be treated date fields.
 * This is an unfortunate workaround that is neccessary because the DataObject
 * generator script does not make a difference between any other datatypes than
 * string and integer. When it does, this can be dropped.</li>
 * <li>textFields:
 * A simple array of field names indicating which of the fields in a particular table/class
 * are actually to be treated as textareas.
 * This is an unfortunate workaround that is neccessary because the DataObject
 * generator script does not make a difference between any other datatypes than
 * string and integer. When it does, this can be dropped.</li></ul>
 * 
 * Note for PHP5-users: These properties have to be public!
 *
 * Most basic usage:
 * <code>
 * $do =& new MyDataObject();
 * // Insert "$do->get($some_id);" here to edit an existing object instead of making a new one 
 * $fg =& DB_DataObject_FormBuilder::create($do);
 * $form =& $fg->getForm();
 * if ($form->validate()) {
 *     $form->process($fg, false);
 *     $form->freeze();
 * }
 * $form->display();
 * </code>
 * 
 * @package  DB_DataObject_FormBuilder
 * @author   Markus Wolff <mw21st@php.net>
 * @version  $Id$
 */


// Import requirements
require_once('DB/DataObject.php');
require_once('HTML/QuickForm.php');

class DB_DataObject_FormBuilder
{
    /**
     * Add a header to the form - if set to true, the form will
     * have a header element as the first element in the form.
     *
     * @access public
     * @see form_header_text
     */
    var $add_form_header = true;

    /**
     * Text for the form header. If not set, the name of the database
     * table this form represents will be used.
     *
     * @access public
     * @see add_form_header
     */
    var $form_header_text = null;

    /**
     * Text that is displayed as an error message if a validation rule
     * is violated by the user�s input.
     *
     * @access public
     * @see rule_violation_message
     */
    var $rule_violation_message = 'The value you have entered is not valid.';

    /**
     * If you want to use the generator on an existing form object, pass it
     * to the factory method within the options array, element name: 'form'
     * (who would have guessed?)
     *
     * @access protected
     * @see DB_DataObject_Formbuilder()
     */
    var $form = false;

    /**
     * If set to TRUE, the current DataObject�s validate method is being called
     * before the form data is processed. If errors occur, no insert/update operation
     * will be made on the database. Use getValidationErrors() to retrieve the reasons
     * for a failure.
     * Defaults to FALSE.
     *
     * @access public
     */
    var $validateOnProcess = false;

    /**
     * Contains the last validation errors, if validation checking is enabled.
     *
     * @access protected
     */
    var $_validationErrors = false;


    /**
     * DB_DataObject_FormBuilder::create()
     *
     * Factory method. Although not really needed at the moment, it is the recommended
     * method to make a new object instance. Benefits: Checks the passed parameters and
     * returns a PEAR_Error object in case something is wrong. Also, it will make
     * your code forward-compatible to future versions of this class, which might include
     * other types or forms, resulting in this being a stripped-down base class that
     * returns a specialized class for the desired purpose (i.e. for generating GTK
     * form elements for use with PHP-GTK, WML forms for WAP...).
     *
     * Options can be:
     * - 'form' : A reference to an existing HTML_QuickForm object. If not set, a new one
     *   will be created (default behaviour).
     * - 'rule_violation_message' : See description of similarly-named class property
     * - 'add_form_header' : See description of similarly-named class property
     * - 'form_header_text' : See description of similarly-named class property
     * 
     * @param object $do      The DB_DataObject-derived object for which a form shall be built
     * @param array $options  An associative array of options. Pass empty array if you want defaults.
     * @access public
     * @returns object        DB_DataObject_FormBuilder or PEAR_Error object
     */
    function &create(&$do, &$options)
    {
        if (is_a($do, 'db_dataobject')) {
            $obj = &new DB_DataObject_FormBuilder($do, $options);
            return $obj;    
        }
        return (new PEAR_Error('DB_DataObject_FormBuilder::create(): Object does not extend DB_DataObject.',
                               DB_DATAOBJECT_FORMBUILDER_ERROR_WRONGCLASS)
               );
    }


    /**
     * DB_DataObject_FormBuilder::DB_DataObject_FormBuilder()
     *
     * The class constructor.
     * 
     * @param object $do      The DB_DataObject-derived object for which a form shall be built
     * @param array $options  An associative array of options. Pass empty array if you want defaults.
     * @access public
     */
    function DB_DataObject_FormBuilder(&$do, &$options)
    {
        if (is_array($options)) {
            reset($options);
            while (list($key, $value) = each($options)) {
                if (isset($this->key)) {
                    // If the option name is 'form', it *must* be a QuickForm object!
                    if (strtolower($key) == 'form' && !is_a($value, 'html_quickform')) {
                        $this->debug('FormBuilder: Option "form" is not a HTML_QuickForm object!');
                    } else {
                        // If value is an object, assign by reference
                        if (is_object($value)) {
                            $this->$key =& $value;
                        } else {
                            $this->$key = $value;
                        }
                    }
                }
            }
        }
        $this->_do = &$do;
    }


    /**
     * DB_DataObject_FormBuilder::_generateForm()
     *
     * Builds a simple HTML form for the current DataObject. Internal function, called by
     * the public getForm() method. You can override this in child classes if needed, but
     * it�s also possible to leave this as it is and just override the getForm() method
     * to simply fine-tune the auto-generated form object (i.e. add/remove elements, alter
     * options, add/remove rules etc.).
     * If a key with the same name as the current field is found in the preDefElements
     * property, the QuickForm element object contained in that array will be used instead
     * of auto-generating a new one. This allows for complete step-by-step customizing of
     * your forms.
     *
     * @param string $action   The form action. Optional. If set to false (default), PHP_SELF is used.
     * @param string $target   The window target of the form. Optional. Defaults to '_self'.
     * @param string $formName The name of the form, will be used in "id" and "name" attributes. If set to false (default), the class name is used
     * @param string $method   The submit method. Defaults to 'post'.
     * @return object
     * @access protected
     */    
    function &_generateForm($action=false, $target='_self', $formName=false, $method='post')
    {
        global $_DB_DATAOBJECT;

        if ($formName === false) {
            $formName = get_class($this->_do);
        }
        if ($action === false) {
            $action = $_SERVER['PHP_SELF'];   
        }

        // If there is an existing QuickForm object, use that one. If not, make a new one.
        if (!is_a($this->form, 'html_quickform')) {
            $form =& new HTML_QuickForm($formName, $method, $action, $target);
        } else {
            $form =& $this->form;
        }

        // Initialize array with default values
        $formValues = $this->_do->toArray();

        // Add a header to the form - set _add_form_header property to false to prevent this
        if ($this->add_form_header == true) {
            if (!is_null($this->form_header_text)) {
               $form->addElement('header', '', $this->form_header_text);
            } else {
               $form->addElement('header', '', $this->_do->tableName());
            }
        }

        // Go through all table fields and create appropriate form elements
        $keys = $this->_do->keys();

        //REORDER
        $elements = $this->_reorderElements();
        if($elements === false) { //no sorting necessary
            $elements = $this->_do->table();
        }

        //GROUPING
        $groupelements = array_keys((array)$this->_do->preDefGroups);

        foreach ($elements as $key=>$type) {
            // Check if current field is primary key. If so, make hidden field
            if (in_array($key, $keys)) {
               $form->addElement('hidden', $key, $this->getFieldLabel($key));
            } else {
                if (isset($this->_do->preDefElements[$key]) && is_object($this->_do->preDefElements[$key])) {
                    // Use predefined form field
                    $element = $this->_do->preDefElements[$key];
                } else {
                    // No predefined object available, auto-generate new one
                    $elValidator = false;
                    $elValidRule = false;
                    // Try to determine field types depending on object properties
                    if (is_array($this->_do->dateFields) && in_array($key,$this->_do->dateFields)) {
                        $element = HTML_QuickForm::createElement('dategroup', $key, $this->getFieldLabel($key), array('format'=>$_DB_DATAOBJECT['CONFIG']['date_element_format']));
                        
                        switch($_DB_DATAOBJECT['CONFIG']['db_date_format']){
                            case "1": //iso
                                $formValues[$key] = $this->_date2array($this->_do->$key);
                            break;
                            
                        }
                    } elseif (is_array($this->_do->textFields) && in_array($key,$this->_do->textFields)) {
                        $element = HTML_QuickForm::createElement('textarea', $key, $this->getFieldLabel($key));
                    } else {
                        // Auto-detect field types depending on field�s database type
                        switch ($type) {
                            case DB_DATAOBJECT_INT:
                                $links = $this->_do->links();
                                if (is_array($links) && array_key_exists($key, $links)) {
                                    $opt = $this->getSelectOptions($key);
                                    $element = HTML_QuickForm::createElement('select', $key, $this->getFieldLabel($key), $opt);
                                } else {
                                    $element = HTML_QuickForm::createElement('text', $key, $this->getFieldLabel($key));
                                    $elValidator = 'numeric';
                                }
                                unset($links);
                                break;
                            case DB_DATAOBJECT_DATE: // TODO
                            case DB_DATAOBJECT_TIME: // TODO
                            case DB_DATAOBJECT_BOOL: // TODO
                            case DB_DATAOBJECT_TXT:
                                $element = HTML_QuickForm::createElement('textarea', $key, $this->getFieldLabel($key));
                                break;
                            case DB_DATAOBJECT_STR: 
                                // If field content contains linebreaks, make textarea - otherwise, standard textbox
                                if (strstr($this->_do->$key, "\n")) {
                                    $element = HTML_QuickForm::createElement('textarea', $key, $this->getFieldLabel($key));
                                } else {                                    
                                    $element = HTML_QuickForm::createElement('text', $key, $this->getFieldLabel($key));
                                }
                                break;
                            default:
                                $element = HTML_QuickForm::createElement('text', $key, $this->getFieldLabel($key));
                        } // End switch
                    } // End else                

                    if ($elValidator !== false) {
                        $rules[$key][] = array('validator' => $elValidator, 'rule' => $elValidRule);
                    } // End if
                                        
                } // End else
            } // End else
                    
            //GROUP OR ELEMENT ADDITION
            if(in_array($key, $groupelements)) {
                $group = $this->_do->preDefGroups[$key];
                $groups[$group][] = $element;
            } else {
                $form->addElement($element);
            } // End if     
            

            //VALIDATION RULES
            if (isset($rules[$key])) {
                while(list($n, $rule) = each($rules[$key])) {
                    if ($rule['rule'] === false) {
                        $form->addRule($key, $this->rule_violation_message, $rule['validator']);
                    } else {
                        $form->addRule($key, $this->rule_violation_message, $rule['validator'], $rule['rule']);
                    } // End if
                } // End while
            } // End if     

        } // End foreach    

        //GROUP SUBMIT
        $flag = true;
        if(in_array('__submit__', $groupelements)) {
            $group = $this->_do->preDefGroups['__submit__'];
            if(count($groups[$group]) > 1) {
                $groups[$group][] = HTML_QuickForm::createElement('submit', '__submit__', 'Submit');
                $flag = false;
            } else {
                $flag = true;
            }   
        } 

        //GROUPING  
        if(is_array($groups)) { //apply grouping
            while(list($grp, $elements) = each($groups)) {
                if(count($elements) == 1) {  
                    $form->addElement($elem);
                } elseif(count($elements) > 1) { 
                    $form->addGroup($elements, $grp, $grp, '&nbsp;');
                }
            }       
        }

        //ELEMENT SUBMIT
        if($flag) { 
            $form->addElement('submit', '__submit__', 'Submit');
        }
                
        // Assign default values to the form
        $form->setDefaults($formValues);        
        return $form;
    }


    /**
     * DB_DataObject_FormBuilder::_reorderElements()
     * 
     * Changes the order in which elements are being processed, so that
     * you can use QuickForm�s default renderer or dynamic templates without
     * being dependent on the field order in the database.
     *
     * Make a class property named "preDefOrder" in your DataObject-derived classes
     * which contains an array with the correct element order to use this feature.
     * 
     * @return mixed  Array in correct order or FALSE if reordering was not possible
     * @access protected
     */
    function _reorderElements() {
        if(is_array($this->_do->preDefOrder) && count($this->_do->preDefOrder) == count($this->_do->table())) {
            $this->debug("<br/>...reordering elements...<br/>");
            $elements = $this->_do->table();
            while(list($index, $elem) = each($this->_do->preDefOrder)) {
                if(in_array($elem, array_keys($elements))) {
                    $ordered[$elem] = $elements[$elem]; //key=>type
                } else {
                    $this->debug("<br/>...reorder not supported: invalid element(key) found...<br/>");
                    return false;
                }
            }
            return $ordered;
        } else {
            $this->debug("<br/>...reorder not supported...<br/>");
            return false;
        }
    }



    /**
     * DB_DataObject_FormBuilder::getFieldLabel()
     * 
     * Returns the label for the given field name. If no label is specified,
     * the fieldname will be returned with ucfirst() applied.
     *
     * @param $fieldName  string  The field name
     * @return string
     * @access public
     */
    function getFieldLabel($fieldName)
    {
        if (isset($this->_do->fieldLabels[$fieldName])) {
            return $this->_do->fieldLabels[$fieldName];
        }
        return ucfirst($fieldName); 
    }


    /**
     * DB_DataObject_FormBuilder::getSelectOptions()
     *
     * Returns an array of options for use with the HTML_QuickForm "select" element.
     * It will try to fetch all related objects (if any) for the given field name and
     * build the array. For the display name of the option, it will try to use either
     * the linked object�s property "select_display_field". If that one is not present,
     * it will try to use the global configuration setting "select_display_field".
     * Can also be called with a second parameter containing the name of the display
     * field - this will override all other settings.
     * Same goes for "select_order_field", which determines the field name used for
     * sorting the option elements. If neither a config setting nor a class property
     * of that name is set, the display field name will be used.
     *
     * @param string $field         The field to fetch the links from. You should make sure the field actually *has* links before calling this function (see: DB_DataObject::links())
     * @param string $displayField  (Optional) The name of the field used for the display text of the options
     * @return array
     * @access public
     */
    function getSelectOptions($field, $displayfield=false)
    {
        global $_DB_DATAOBJECT;
        $links = $this->_do->links();
        $link = explode(':', $links[$field]);
        $opts = DB_DataObject::factory($link[0]);
        if (is_a($opts, 'db_dataobject')) {
            if (isset($opts->_primary_key)) {
                $pk = $opts->_primary_key;
            } else {
                $k = $opts->keys();
                $pk = $k[0];
            }
            if ($displayfield == false) {
                if (!isset($opts->select_display_field) || is_null($opts->select_display_field)) {
                    $displayfield = $_DB_DATAOBJECT['CONFIG']['select_display_field'];
                } else {
                    $displayfield = $opts->select_display_field;
                }
            }
            if (!isset($opts->select_order_field) || is_null($opts->select_order_field)) {
                if (isset($_DB_DATAOBJECT['CONFIG']['select_display_field']) && !empty($_DB_DATAOBJECT['CONFIG']['select_display_field'])) {
                    $order = $_DB_DATAOBJECT['CONFIG']['select_display_field'];
                } else {
                    $order = $displayfield;
                }
            } else {
                $order = $opts->select_order_field;
            }
            $opts->orderBy($order);
            $list = array();

            // FINALLY, let�s see if there are any results
            if ($opts->find() > 0) {
                while ($opts->fetch()) {
                    $list[$opts->$pk] = $opts->$displayfield;   
                }
            }

            return $list;
        }
        $this->debug('Error: '.get_class($opts).' does not inherit from DB_DataObject');
        return array();
    }


    /**
     * DB_DataObject_FormBuilder::getForm()
     *
     * Returns a HTML form that was automagically created by _generateForm().
     * You need to use the get() method before calling this one in order to 
     * prefill the form with the retrieved data.
     * 
     * If you have a method named "preGenerateForm()" in your DataObject-derived class,
     * it will be called before _generateForm(). This way, you can create your own elements
     * there and add them to the "preDefElements" property, so they will not be auto-generated.
     *
     * If you have your own "getForm()" method in your class, it will be called <b>instead</b> of
     * _generateForm(). This enables you to have some classes that make their own forms completely
     * from scratch, without any auto-generation. Use this for highly complex forms. Your getForm()
     * method needs to return the complete HTML_QuickForm object by reference.
     *
     * If you have a method named "postGenerateForm()" in your DataObject-derived class, it will
     * be called after _generateForm(). This allows you to remove some elements that have been
     * auto-generated from table fields but that you don�t want in the form.
     *
     * Many ways lead to rome.
     *
     * @param string $action   The form action. Optional. If set to false (default), $_SERVER['PHP_SELF'] is used.
     * @param string $target   The window target of the form. Optional. Defaults to '_self'.
     * @param string $formName The name of the form, will be used in "id" and "name" attributes. If set to false (default), the class name is used, prefixed with "frm"
     * @param string $method   The submit method. Defaults to 'post'.
     * @return object 
     * @access public
     */
    function &getForm($action=false, $target='_self', $formName=false, $method='post')
    {
        if (method_exists($this->_do, 'pregenerateform')) {
            $this->_do->preGenerateForm($this);
        }
        if (method_exists($this->_do, 'getform')) {
            $obj = $this->_do->getForm($action, $target, $formName, $method);
        } else {
            $obj = &$this->_generateForm($action, $target, $formName, $method);
        }
        if (method_exists($this->_do, 'postgenerateform')) {
            $this->_do->postGenerateForm($obj);
        }
        return($obj);   
    }


    /**
     * DB_DataObject_FormBuilder::_date2array()
     *
     * Takes a string representing a date or a unix timestamp and turns it into an 
     * array suitable for use with the QuickForm data element.
     * When using a string, make sure the format can be handled by PHP's strtotime() function!
     *
     * @param mixed $date   A unix timestamp or the string represantation of a data, compatible to strtotime()
     * @return array
     * @access protected
     */
    function _date2array($date)
    {
        if (is_string($date)) {
            $time = strtotime($date);
        } elseif (is_int($date)) {
            $time = $date;
        } else {
            $time = time();
        } 

        $da = array();
        $da['d'] = date('d', $time);
        $da['M'] = date('m', $time);
        $da['Y'] = date('Y', $time); 

        return $da;
    }


    /**
     * DB_DataObject_FormBuilder::_array2date()
     *
     * Takes a date array as used by the QuickForm date element and turns it back into
     * a string representation suitable for use with a database date field (format 'YYYY-MM-DD').
     * If second parameter is true, it will return a unix timestamp instead.
     *
     * @param array $date   An array representation of a date, as user in HTML_QuickForm's date element
     * @param boolean $timestamp  Optional. If true, return a timestamp instead of a string. Defaults to false.
     * @return mixed
     * @access protected
     */
    function _array2date($date, $timestamp=false)
    {
        if (is_array($date) && checkdate($date['M'], $date['d'], $date['Y'])) {
            $strDate = $date['Y'].'-'.$date['M'].'-'.$date['d'];
        } elseif (is_int($date) && $timestamp==true) {
            $strDate = strtotime($date['Y'].'-'.$date['M'].'-'.$date['d']);
        } else {
            $strDate = date('Y-m-d', time());
        }
        return $strDate;
    }

    /**
     * DB_DataObject_FormBuilder::validateData()
     *
     * Makes a call to the current DataObject�s validate() method and returns the result.
     *
     * @return mixed
     * @access public
     * @see DB_DataObject::validate()
     */
    function validateData()
    {
        $this->_validationErrors = $this->_do->validate();
        return $this->_validationErrors;
    }

    /**
     * DB_DataObject_FormBuilder::getValidationErrors()
     *
     * Returns errors from data validation. If errors have occured, this will be
     * an array with the fields that have errors, otherwise a boolean.
     *
     * @return mixed
     * @access public
     * @see DB_DataObject::validate()
     */
    function getValidationErrors()
    {
        return $this->_validationErrors;
    }


    /**
     * DB_DataObject_FormBuilder::processForm()
     *
     * This will take the submitted form data and put it back into the object's properties.
     * If the primary key is not set or NULL, it will be assumed that you wish to insert a new
     * element into the database, so DataObject's insert() method is invoked.
     * Otherwise, an update() will be performed.
     * <i><b>Careful:</b> If you�re using natural keys or cross-referencing tables where you don�t have
     * one dedicated primary key, this will always assume that you want to do an update! As there
     * won�t be a matching entry in the table, no action will be performed at all - the reason
     * for this behaviour can be very hard to detect. Thus, if you have such a situation in one
     * of your tables, simply override this method so that instead of the key check it will try
     * to do a SELECT on the table using the current settings. If a match is found, do an update.
     * If not, do an insert.</i>
     * This method is perfect for use with QuickForm's process method. Example:
     * <code>
     * if ($form->validate()) {
     *     $form->freeze();
     *     $form->process(array(&$formGenerator,'processForm'), false);
     * }
     * </code>
     * Always remember to pass your objects by reference - otherwise, if the operation was
     * an insert, the primary key won�t get updated with the new database ID because processForm()
     * was using a local copy of the object!
     *
     * If a method named "preProcess()" exists in your derived class, it will be called before
     * processForm() starts doing its magic. The data that has been submitted by the form
     * will be passed to that method as a parameter.
     * Same goes for a method named "postProcess()", with the only difference - you might
     * have guessed this by now - that it�s called after the insert/update operations have
     * been done. Use this for filtering data, notifying users of changes etc.pp. ...
     *
     * @param array $values   The values of the submitted form
     * @return boolean        TRUE if database operations were performed, FALSE if not
     * @access public
     */
    function processForm($values)
    {
        $this->debug("<br>...processing form data...<br>");
        if (method_exists('preprocess', $this->_do)) {
            $this->_do->preProcess($values);
        }

        foreach ($values as $field=>$value) {
            $this->debug("Field $field ");
            if ((isset($this->_do->$field) || @is_null($this->_do->$field)) && $field != '__submit__') {
                if (is_array($value)) {
                    $this->debug(" (converting date) ");
                    $value = $this->_array2date($value);
                }
                $this->_do->$field = $value;
                $this->debug("is substituted with '$value'.\n");
            } else {
                $this->debug("is not a valid field.\n");
            }
        }

        $dbOperations = true;
        if ($this->validateOnProcess === true) {
            $this->debug('Validating data... ');
            if (is_array($this->validateData())) {
                $dbOperations = false;
            }
        }

        if ($dbOperations) {
            $insert = false;
            if (isset($this->_do->_primary_key)) {
                $pk = $this->_do->_primary_key;
            } else {
                $keys = $this->_do->keys();
                if (is_array($keys) && isset($keys[0])) {
                    $pk = $keys[0];
                }
            }
            if (empty($this->_do->$pk) || is_null($this->_do->$pk)) {
                $insert = true;
            }
            if ($insert == true) {
                $id = $this->_do->insert();
                $this->debug("ID ($pk) of the new object: $id <br>\n");
            } else {
                $this->_do->update();
                $this->debug("Object updated.<br>\n");
            }
        }

        if (method_exists('postprocess', $this->_do)) {
            $this->_do->postProcess($values);
        }

        return $dbOperations;
    }


    /**
     * DB_DataObject_FormBuilder::debug()
     *
     * Outputs a debug message, if the debug setting in the DataObject.ini file is
     * set to 1 or higher.
     *
     * @param string $message  The message to printed to the browser
     * @access public
     * @see DB_DataObject::debugLevel()
     */
    function debug($message)
    {
        if (DB_DataObject::debugLevel() > 0) {
            echo "<pre><b>FormBuilder:</b> $message</pre>\n";
        }
    }

}

?>