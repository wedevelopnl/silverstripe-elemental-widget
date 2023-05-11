<?php

declare(strict_types=1);

namespace WeDevelop\ElementalWidget\Element;

use DNADesign\Elemental\Models\BaseElement;
use SGN\HasOneEdit\HasOneEdit;
use Sheadawson\DependentDropdown\Forms\DependentDropdownField;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use UncleCheese\DisplayLogic\Forms\Wrapper;
use WeDevelop\ElementalWidget\Model\Widget;

/**
 * @property int $WidgetID
 * @property bool $SourcesFromCollection
 * @property string $WidgetClass
 * @method Widget Widget()
 */
class ElementWidget extends BaseElement
{
    /** @config */
    private static string $table_name = 'ElementWidget';
    /** @config */
    private static string $singular_name = 'Widget Element';
    /** @config */
    private static string $plural_name = 'Widget Elements';
    /** @config */
    private static string $description = 'Container for a widget';
    /** @config */
    private static string $icon = 'font-icon-cog';

    /**
     * @var array<string, string>
     * @config
     */
    private static array $db = [
        'SourcesFromCollection' => 'Boolean(1)',
        'WidgetClass' => 'Varchar(255)',
    ];

    /**
     * @var array<string, string>
     * @config
     */
    private static array $has_one = [
        'Widget' => Widget::class,
    ];

    /**
     * @var array<string, mixed>
     * @config
     */
    private static array $defaults = [
        'SourcesFromCollection' => true,
    ];

    /** @config */
    private static bool $inline_editable = false;

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'WidgetID',
            'WidgetClass',
            'SourcesFromCollection',
        ]);

        $widgetFn = static function ($val) {
            $options = $val::get()->filter(['IsPartOfCollection' => true])->map();

            $options->unshift(0, 'Select a widget');

            return $options;
        };

        $fields->addFieldsToTab('Root.Main', [
            $typeDropdown = DropdownField::create(
                'WidgetClass',
                'Widget Type',
                self::getWidgetTypes(),
                $this->Widget() && $this->Widget()->exists() ? $this->Widget()->ClassName : ''
            )
                ->setHasEmptyDefault(true)
                ->setEmptyString('Select a widget type'),
            CheckboxField::create('SourcesFromCollection', 'Pick from the collection'),
            ReadOnlyField::create(
                'CurrentWidgetID',
                'Current Widget ID',
                $this->Widget() && $this->Widget()->exists() ? $this->Widget()->ID : ''
            ),
            Wrapper::create(
                DependentDropdownField::create(
                    'WidgetDropdown',
                    'Widget',
                    $widgetFn,
                    $this->Widget()->ID
                )->setDepends($typeDropdown)
            )->displayIf('SourcesFromCollection')->isChecked()->end(),
            Wrapper::create(
                HeaderField::create('Save this block to create a non-collection widget of the selected type')
            )
                ->displayIf('SourcesFromCollection')->isNotChecked()
                ->andIf('WidgetClass')->isNotEmpty()
                ->andIf('CurrentWidgetID')->isNotEmpty()
                ->end(),
        ]);

        if ($this->Widget() && $this->Widget()->exists()) {
            $widgetFields = $this->Widget()->getCMSFields();

            $widgetFields->removeByName([
                'Title',
                'Main',
            ]);

            // We'll loop over the widget fields to properly set the name so it maps to the relation
            // instead of trying to edit it on the element itself
            foreach($widgetFields->dataFields() as $field) {
                $field->setName('Widget' . HasOneEdit::FIELD_SEPARATOR . $field->getName());
            }

            if ($this->SourcesFromCollection) {
                $fields->addFieldToTab(
                    'Root.Widget',
                    HeaderField::create('WarningHeader', 'Please note that editing settings here will edit the widget on ALL pages.')
                );
            }

            $fields->addFieldsToTab(
                'Root.Widget',
                $widgetFields->toArray()
            );
        }

        return $fields;
    }
    public function canCreate($member = null, $context = []): ?bool
    {
        if (static::class !== self::class) {
            return false;
        }

        return parent::canCreate($member, $context);
    }

    public function RenderWidget(): DBHTMLText|string
    {
        $widget = $this->Widget();

        if ($widget->exists()) {
            $widget->setElement($this);
            return $widget->forTemplate();
        }

        return '';
    }

    public function getType(): string
    {
        return 'Widget';
    }

    /**
     * @return array<string, string>
     */
    public static function getWidgetTypes(): array
    {
        $widgetClassInfo = ClassInfo::subclassesFor(Widget::class, false);
        $widgets = [];

        foreach ($widgetClassInfo as $uppercase) {
            $singleton = $uppercase::singleton();
            $widgets[$uppercase] = $singleton->i18n_singular_name();
        }

        return $widgets;
    }

    public function onBeforeWrite(): void
    {
        $widgetExists = $this->Widget()->exists();

        if ($this->Title && $widgetExists && !$this->Widget()->Title) {
            $this->Widget()->Title = $this->Title;
            $this->Widget()->write();
        }

        if (!$this->Title && $widgetExists && $this->Widget()->Title) {
            $this->Title = $this->Widget()->Title;
        }

        // We don't have a link to a widget instance, but we want to create a unique widget not in the collection to
        // link to this element.
        if (!$this->SourcesFromCollection && $this->WidgetClass && !$widgetExists) {
            $class = $this->WidgetClass;

            /** @var Widget $widgetInstance */
            $widgetInstance = $class::create();
            $widgetInstance->IsPartOfCollection = false;
            $widgetInstance->write();

            $this->WidgetID = $widgetInstance->ID;

            parent::onBeforeWrite();
            return;
        }

        $sourcesFromCollectionChanged = array_key_exists(
            'SourcesFromCollection',
            $this->getChangedFields(
                true,
                DataObject::CHANGE_VALUE
            )
        );

        // We had a link to a widget from the collection, but now we changed that to no longer be the case,
        // so we'll duplicate the current widget and set it to not be part of the collection so this can be
        // freely edited without worrying about causing changes to other pages.
        if ($sourcesFromCollectionChanged && !$this->SourcesFromCollection && $this->WidgetClass && $widgetExists) {
            $currentWidget = $this->Widget();
            $duplicatedWidget = $currentWidget->duplicate(false);
            $duplicatedWidget->IsPartOfCollection = false;
            $duplicatedWidget->write();

            $this->WidgetID = $duplicatedWidget->ID;

            parent::onBeforeWrite();
            return;
        }

        // We had a link to a widget not in the collection, we'll just change the widget that is already linked to
        // be part of the collection.
        if ($sourcesFromCollectionChanged && $this->SourcesFromCollection && $widgetExists) {
            $this->Widget()->IsPartOfCollection = true;
            $this->Widget()->write();

            parent::onBeforeWrite();
            return;
        }

        parent::onBeforeWrite();
    }

    public function getIcon(): ?DBHTMLText
    {
        if ($this->Widget()->exists() && method_exists($this->Widget(), 'getIcon')) {
            $data = ArrayData::create([]);

            $iconClass = $this->Widget()->getIcon();

            if ($iconClass) {
                $data->IconClass = $iconClass;

                if ($this->hasExtension(Versioned::class)) {
                    $data->IsVersioned = true;
                    if ($this->isOnDraftOnly()) {
                        $data->VersionState = 'draft';
                        $data->VersionStateTitle = _t(
                            'SilverStripe\\Versioned\\VersionedGridFieldState\\VersionedGridFieldState.ADDEDTODRAFTHELP',
                            'Item has not been published yet'
                        );
                    } elseif ($this->isModifiedOnDraft()) {
                        $data->VersionState = 'modified';
                        $data->VersionStateTitle = _t(
                            'SilverStripe\\Versioned\\VersionedGridFieldState\\VersionedGridFieldState.MODIFIEDONDRAFTHELP',
                            'Item has unpublished changes'
                        );
                    }
                }

                return $data->renderWith(BaseElement::class . '/PreviewIcon');
            }
        }

        return parent::getIcon();
    }

    public function setWidgetDropdown($widgetID): void
    {
        // This is a workaround for the onBeforeWrite handler, we cannot set the dropdown to WidgetID directly, since
        // that would result in it resetting to 0 if we try to set a widget NOT from the collection, since it is not an
        // option in the dropdown.
        if ($this->SourcesFromCollection) {
            $this->WidgetID = $widgetID;
        }
    }

    /**
     * @return array<string, string>
     */
    protected function provideBlockSchema(): array
    {
        $schema = parent::provideBlockSchema();

        // This is set to show the type in the content preview (where it would otherwise say: `No preview available`)
        if ($this->WidgetClass) {
            $schema['content'] = singleton($this->WidgetClass)->i18n_singular_name();

            if ($this->SourcesFromCollection) {
                $schema['content'] .= ': ' . $this->Widget()->Title;
            }
        }

        return $schema;
    }
}
