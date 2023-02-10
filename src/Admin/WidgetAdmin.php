<?php

namespace WeDevelop\ElementalWidget\Admin;

use WeDevelop\ElementalWidget\Model\Widget;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\ClassInfo;

class WidgetAdmin extends ModelAdmin
{
    /** @config */
    private static string $url_segment = 'widgets';

    /** @config */
    private static string $menu_title = 'Widgets';

    /** @config */
    private static string $menu_icon_class = 'font-icon-menu-modaladmin';

    /** @config */
    private static array $managed_models = [];

    public function getManagedModels(): array
    {
        $models = self::config()->get('managed_models');
        $widgetModelsInfo = ClassInfo::subclassesFor(Widget::class, false);
        $widgetModels = [];

        foreach ($widgetModelsInfo as $lowercase => $uppercase) {
            $widgetModels[] = $uppercase;
        }

        self::config()->set('managed_models', array_merge($models, $widgetModels));

        return parent::getManagedModels();
    }
}
