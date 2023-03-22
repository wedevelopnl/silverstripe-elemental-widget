<?php

namespace WeDevelop\ElementalWidget\Admin;

use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\DataList;
use WeDevelop\ElementalWidget\GridField\PromoteToCollectionGridFieldAction;
use WeDevelop\ElementalWidget\Model\Widget;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\GridField\GridField;

class WidgetAdmin extends ModelAdmin
{
    private const PAGINATION_LENGTH = 10;

    /** @config */
    private static string $url_segment = 'widgets';

    /** @config */
    private static string $menu_title = 'Widgets';

    /** @config */
    private static string $menu_icon_class = 'font-icon-menu-modaladmin';

    /** @config */
    private static array $managed_models = [];

    public function getGridField(): GridField
    {
        $gridField = parent::getGridField();
        $dataList = $gridField->getList();

        if ($dataList instanceof DataList) {
            $filteredList = $dataList->filter(['IsPartOfCollection' => true]);
            $gridField->setList($filteredList);

            $config = $gridField->getConfig();

            if ($config) {
                $config->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(self::PAGINATION_LENGTH);
                $config->removeComponentsByType([
                    GridFieldImportButton::class,
                    GridFieldPrintButton::class,
                    GridFieldExportButton::class,
                ]);
            }
        }

        return $gridField;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm();
        $formFields = $form->Fields();
        $currentModelClass = $this->getModelClass();
        $dataList = $currentModelClass::get()->filter(['IsPartOfCollection' => false]);

        $config = GridFieldConfig::create()->addComponents(
            GridFieldSortableHeader::create(),
            GridFieldButtonRow::create('before'),
            GridFieldFilterHeader::create(),
            GridFieldDataColumns::create(),
            GridFieldPaginator::create(self::PAGINATION_LENGTH),
            GridField_ActionMenu::create(),
            GridFieldEditButton::create(),
            GridFieldDeleteAction::create(),
            PromoteToCollectionGridFieldAction::create(),
            GridFieldDetailForm::create(null, false, false),
        );

        $gridField = GridField::create(
            $this->sanitiseClassName($this->sanitiseClassName($currentModelClass)) . '-nc',
            $this->sanitiseClassName($this->sanitiseClassName($currentModelClass)),
            $dataList,
            $config,
        );

        $gridField->setForm($form);
        $gridField->getConfig()->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(10);

        $formFields->insertBefore(
            $this->sanitiseClassName($currentModelClass),
            HeaderField::create('collectionHeader', 'Collection widgets', 1)
        );

        $formFields->insertAfter(
            'collectionHeader',
            HeaderField::create('collectionHeaderExplained', 'These widgets are part of the "collection" and can have multiple widget elements associated with them.', 2)
        );

        $formFields->push(HeaderField::create('nonCollectionHeader', 'Non-collection widgets', 1));
        $formFields->push(HeaderField::create('nonCollectionExplained', 'These widgets are not part of the "collection" and are only associated with 1 widget element.', 2));
        $formFields->push($gridField);

        $gridField->setModelClass($currentModelClass);

        return $form;
    }

    public function getManagedModels(): array
    {
        $models = self::config()->get('managed_models');
        $widgetModelsInfo = ClassInfo::subclassesFor(Widget::class, false);
        $widgetModels = [];

        foreach($widgetModelsInfo as $lowercase => $uppercase) {
            $widgetModels[] = $uppercase;
        }

        self::config()->set('managed_models', array_merge($models, $widgetModels));

        return parent::getManagedModels();
    }
}
