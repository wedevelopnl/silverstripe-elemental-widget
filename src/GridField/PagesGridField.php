<?php

namespace WeDevelop\ElementalWidget\GridField;

use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Forms\GridField\GridField;

class PagesGridField extends GridField
{
    public function Link($action = null): string
    {
        return $action === 'item' ? CMSPageEditController::singleton()->Link('show') : parent::Link();
    }

    public function getModelClass(): string
    {
        return \Page::class;
    }
}
