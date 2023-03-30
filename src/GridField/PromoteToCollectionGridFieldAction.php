<?php

declare(strict_types=1);

namespace WeDevelop\ElementalWidget\GridField;

use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Control\Controller;
use WeDevelop\ElementalWidget\Model\Widget;

class PromoteToCollectionGridFieldAction extends AbstractGridFieldComponent implements GridField_ColumnProvider, GridField_ActionProvider, GridField_ActionMenuItem
{
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns, true)) {
            $columns[] = 'Actions';
        }
    }

    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__col-compact'];
    }

    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName === 'Actions') {
            return ['title' => ''];
        }
    }

    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    public function getColumnContent($gridField, $record, $columnName)
    {
        if (!$record->canEdit()) {
            return [];
        }

        return $this->getFormAction($gridField, $record, $columnName)->Field();
    }

    public function getActions($gridField)
    {
        return ['dopromotetocollection'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName !== 'dopromotetocollection') {
            return;
        }

        // perform your action here
        /** @var Widget $record */
        $record = $arguments['ClassName']::get_by_id($arguments['RecordID']);

        $record->IsPartOfCollection = true;
        $record->write();

        $controller = Controller::curr();

        if (!$controller) {
            return;
        }

        return $controller->redirect($controller->getRequest()->getHeader('referer'));
    }

    public function getTitle($gridField, $record, $columnName)
    {
        return 'Move to collection';
    }

    public function getExtraData($gridField, $record, $columnName)
    {
        $field = $this->getFormAction($gridField, $record, $columnName);

        if ($field) {
            return $field->getAttributes();
        }

        return null;
    }

    public function getGroup($gridField, $record, $columnName)
    {
        $field = $this->getFormAction($gridField, $record, $columnName);

        return $field ? GridField_ActionMenuItem::DEFAULT_GROUP : null;
    }

    private function getFormAction($gridField, $record, $columnName)
    {
        return GridField_FormAction::create(
            $gridField,
            'PromoteToCollection' . $record->ID,
            false,
            'dopromotetocollection',
            [
                'RecordID' => $record->ID,
                'ClassName' => get_class($record),
            ]
        )
            ->addExtraClass('btn btn--icon-md grid-field__icon-action action-menu--handled font-icon-folder-move')
            ->setAttribute('classNames', 'font-icon-folder-move no-ajax')
            ->setTitle('Move to collection');
    }
}
