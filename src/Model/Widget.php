<?php

declare(strict_types=1);

namespace WeDevelop\ElementalWidget\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use WeDevelop\ElementalWidget\Element\ElementWidget;
use WeDevelop\ElementalWidget\GridField\PagesGridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 * @property string $IsPartOfCollection
 * @property string $ImportID
 * @method array<ElementWidget> Elements
 */
class Widget extends DataObject
{
    /** @config */
    private static string $table_name = 'Widget';

    /** @config */
    private static string $icon = 'font-icon-database';

    /**
     * @var array<string, string>
     * @config
     */
    private static array $db = [
        'Title' => 'Varchar(255)',
        'IsPartOfCollection' => 'Boolean(1)',
        'ShowPagesUsedOn' => 'Boolean(1)',
    ];

    /**
     * @var array<string, string>
     * @config
     */
    private static array $has_many = [
        'Elements' => ElementWidget::class,
    ];

    /**
     * @var array<int|string, string>
     * @config
     */
    private static array $summary_fields = [
        'Title',
        'Created',
        'LastEdited' => 'Last edited',
    ];

    /**
     * @var array<string, mixed>
     * @config
     */
    private static array $defaults = [
        'IsPartOfCollection' => true,
    ];

    /**
     * @var array<string>
     * @config
     */
    private static array $searchable_fields = [
        'Title',
        'Created',
        'LastEdited',
    ];

    private ?ElementWidget $element = null;

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName([
            'ImportID',
            'Elements',
        ]);

        if ($this->exists() && $this->ShowPagesUsedOn) {
            $fields->addFieldToTab('Root.PagesUsedOn', PagesGridField::create(
                'PagesUsedOn',
                'Pages used on',
                $this->getPagesUsedOn(),
                GridFieldConfig_RecordViewer::create()
            ));
        }

        return $fields;
    }

    public function getPagesUsedOn(): ArrayList
    {
        $list = new ArrayList();
        /** @var ElementWidget $element */
        foreach ($this->Elements() as $element) {
            $page = $element->getPage();

            if ($page) {
                $list->push($page);
            }
        }
        return $list->removeDuplicates();
    }

    public function setElement(ElementWidget $element): void
    {
        $this->element = $element;
    }

    public function getElement(): ?ElementWidget
    {
        return $this->element;
    }

    public function forTemplate(): DBHTMLText
    {
        return $this->renderWith($this->ClassName);
    }

    public function canView($member = null): bool
    {
        return true;
    }

    public function getIcon(): string
    {
        return self::$icon;
    }
}
