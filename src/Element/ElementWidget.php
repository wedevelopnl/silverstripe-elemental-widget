<?php

namespace WeDevelop\ElementalWidget\Element;

use DNADesign\Elemental\Models\BaseElement;
use Sheadawson\DependentDropdown\Forms\DependentDropdownField;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use WeDevelop\ElementalWidget\Model\Widget;

/**
 * @property int $WidgetID
 * @property bool $IsFromCollection
 * @method Widget Widget()
 */
class ElementWidget extends BaseElement
{
    /** @config */
    private static string $table_name = 'ElementWidget';
    private static string $singular_name = 'Widget Element';
    /** @config */
    private static string $plural_name = 'Widget Elements';
    /** @config */
    private static string $description = 'Container for a widget';
    /** @config */
    private static string $icon = 'font-icon-menu-modaladmin';

    private static array $db = [];

    /** @config */
    public static string $widget_class = '';

    /** @config */
    private static array $has_one = [
        'Widget' => Widget::class,
    ];

    private static bool $inline_editable = false;

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'WidgetID',
            'WidgetClass',
        ]);

        $widgetFn = static function ($val) {
            $options = $val::get()->map();

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
            DependentDropdownField::create(
                'WidgetID',
                'Widget',
                $widgetFn
            )->setDepends($typeDropdown),
        ]);

        if ($this->Widget() && $this->Widget()->exists()) {
            $widgetFields = $this->Widget()->getCMSFields();

            $widgetFields->removeByName([
                'Title',
                'Root.Main',
            ]);

            $fields->addFieldToTab(
                'Root.Widget',
                HeaderField::create('WarningHeader', 'Please note that editing settings here will edit the widget on ALL pages.')
            );

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

    public static function getWidgetTypes(): array
    {
        $widgetClassInfo = ClassInfo::subclassesFor(Widget::class, false);
        $widgets = [];

        foreach ($widgetClassInfo as $lowercase => $uppercase) {
            $singleton = $uppercase::singleton();
            $widgets[$uppercase] = $singleton->i18n_singular_name();
        }

        return $widgets;
    }
}
