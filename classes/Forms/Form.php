<?php

namespace Satispay\Prestashop\Classes\Forms;

if (!defined('_PS_VERSION_')) {
    exit;
}

// Prestashop
use \Context;
use \HelperForm;
use \PrestaShopException;
use \Tools;

//
use \Exception;
use \Satispay;

/**
 * Abstract class for creating PrestaShop forms in the Satispay module.
 *
 * This class provides a base structure for forms, managing common elements
 * like context, success, and error messages. Subclasses must implement 
 * methods for defining the form's unique name, fields, and processing logic.
 */
abstract class Form
{
    /**
     * The Prestashop form helper.
     *
     * @var HelperForm
     */
    protected $form;

    /**
     * The Satispay module.
     *
     * @var Satispay
     */
    protected $module;

    /**
     * The Satispay module.
     *
     * @var Context
     */
    protected $context;

    /**
     * The success message.
     *
     * @var string
     */
    protected $success;

    /**
     * The error message.
     *
     * @var string
     */
    protected $error;

    /**
     * The Form constructor.
     */
    public function __construct($form, $module)
    {
        $this->form = $form;
        
        /** @var Satispay $module */
        $this->module = $module;

        $this->context = Context::getContext();
    }

    /**
     * The unique name of the form.
     *
     * This method must be implemented by each subclass of the Form class 
     * to return a unique identifier for the form, which is used for referencing 
     * it in different contexts within the module. Ensuring uniqueness among all 
     * forms is essential to avoid conflicts.
     *
     * @return string The unique name of the form.
     *
     * @throws Exception If the implementing class does not return a valid name.
     */
    abstract protected function name();

    /**
     * Converts a given field name into a slug.
     *
     * @param string $name The name of the field to be converted.
     *
     * @return string The field slug of the field name.
     */
    protected function field($name)
    {
        $name = Tools::str2url(
            Tools::replaceAccentedChars($name)
        );

        return "{$this->name()}_{$name}";
    }

    /**
     * Retrieves the configuration of form fields specific to the form class.
     *
     * This method must be implemented by each subclass of the Form
     * class to define the fields that the form will contain. The returned
     * array should include field types, labels, names, validation requirements,
     * and any additional options necessary for rendering the form.
     *
     * @return array An associative array representing the form fields,
     *               including their types, labels, names, and options.
     *
     * @throws Exception If the implementing class does not define the fields correctly.
     *                   It is recommended to validate the structure of the returned
     *                   array to ensure compatibility with HelperForm.
     *
     * Example:
     * return [
     *     'form' => [
     *         'legend' => [
     *             'title' => 'My Form Title',
     *         ],
     *         'input' => [
     *             [
     *                 'type' => 'text',
     *                 'label' => 'Field Label',
     *                 'name' => 'field_name',
     *                 'required' => true,
     *             ],
     *         ],
     *         'submit' => [
     *             'title' => 'Submit',
     *             'class' => 'btn btn-default pull-right',
     *         ],
     *     ],
     * ];
     */
    abstract protected function fields();

    /**
     * Retrieves the current values for the form fields.
     *
     * @return array An associative array of field names and their values.
     */
    abstract protected function values();

    /**
     * Renders the form using the PrestaShop HelperForm instance.
     *
     * Generates the HTML representation of the form by using the
     * HelperForm class, utilizing fields defined in the implementing class.
     * Fields must follow PrestaShop's form handling structure.
     *
     * @return string The HTML output of the rendered form.
     *
     * @throws PrestaShopException If there is an error during form generation.
     *                             This may occur if the fields are not 
     *                             defined correctly or if there are issues 
     *                             with the HelperForm instance.
     */
    public function render()
    {
        $this->form->submit_action = $this->name();

        $this->form->tpl_vars['fields_value'] = $this->values();

        return $this->form->generateForm([$this->fields()]);
    }

    /**
     * Get translation for a given module text.
     *
     * Note: $specific parameter is mandatory for library files.
     * Otherwise, translation key will not match for Module library
     * when module is loaded with eval() Module::getModulesOnDisk()
     *
     * @param string $string String to translate
     * @param bool|string $specific filename to use in translation key
     * @param string|null $locale Locale to translate to
     *
     * @return string Translation
     */
    protected function l($string, $specific = false, $locale = null)
    {
        return $this->module->l($string, $specific, $locale);
    }

    /**
     * Validates and processes the form submission.
     *
     * This method checks if the form has been submitted and, if so,
     * initiates the processing of the form data.
     * 
     * @return void
     */
    public function verify()
    {
        if (Tools::isSubmit($this->name())) {
            $this->module->log(get_class($this) . "@verify submitted form {$this->name()}");
            $this->process();
        }
    }

    /**
     * Processes the form submission.
     *
     * This method must be implemented by each subclass to define 
     * the logic for handling form data after submission. It should 
     * include validation, data manipulation, and any necessary 
     * actions based on the submitted values.
     */
    abstract protected function process();
}