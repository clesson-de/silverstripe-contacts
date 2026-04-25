<?php

namespace Clesson\Contacts\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;

/**
 * Extension for the GridFieldDetailForm_ItemRequest class. The only purpose of this class is to add a CSS class to the
 * DetailForm <form> element so that the globally loaded CSS can make a restricted selection.
 *
 * @package Clesson\Contacts
 * @subpackage Extensions
 */
class ContactForm extends Extension
{

    /**
     * @inheritdoc
     */
    public function updateItemEditForm(&$form)
    {
        $config = Config::forClass($this->owner->record->ClassName);
        $className = $config->get('extra_form_class');

        if (!$className) {
            $className = $this->owner->record->ClassName;
            $className = strtolower(str_replace('\\', '-', $className ?? ''));
            $className .= $className ? '-form' : '';
        }

        if ($className) {
            $form->addExtraClass($className);
        }

    }

}
