<?php
/**
 * NOVIUS OS - Web OS for digital communication
 *
 * @copyright  2011 Novius
 * @license    GNU Affero General Public License v3 or (at your option) any later version
 *             http://www.gnu.org/licenses/agpl-3.0.html
 * @link http://www.novius-os.org
 */

$current_application = \Nos\Application::getCurrent();
\Nos\I18n::current_dictionary(array('noviusos_blognews::common'));

$check_permission_category = function($inverted = false) use ($current_application) {
    return function($category) use($inverted, $current_application) {
        $disabled = Nos\User\Permission::exists($current_application.'::category', 'no');
        return $inverted ? !$disabled : $disabled;
    };
};

return array(
    'data_mapping' => array(
        'cat_title' => array(
            'title' => __('Categories'),
        ),
    ),
    'i18n' => array(
        // Crud
        'notification item added' => __('There you go, the category has been added.'),
        'notification item deleted' => __('The category has been deleted.'),

        // General errors
        'notification item does not exist anymore' => __('This category doesn’t exist any more. It has been deleted.'),
        'notification item not found' => __('We cannot find this category.'),

        // Blank slate
        'translate error parent not available in context' => __('We’re afraid this category cannot be added to {{context}} because its <a>parent</a> is not available in this context yet.'),
        'translate error parent not available in language' => __('We’re afraid this category cannot be translated into {{language}} because its <a>parent</a> is not available in this language yet.'),

        // Deletion popup
        'deleting item title' => __('Deleting the category ‘{{title}}’'),

        # Delete action's labels
        'deleting button 1 item' => __('Yes, delete this category'),
        'deleting button N items' => __('Yes, delete these {{count}} categories'),

        '1 item' => __('1 category'),
        'N items' => __('{{count}} categories'),

        # Keep only if the model has the behaviour Contextable
        'deleting with N contexts' => __('This category exists in <strong>{{context_count}} contexts</strong>.'),
        'deleting with N languages' => __('This category exists in <strong>{{language_count}} languages</strong>.'),

        # Keep only if the model has the behaviours Contextable + Tree
        'deleting with N contexts and N children' => __('This category exists in <strong>{{context_count}} contexts</strong> and has <strong>{{children_count}} sub-categories</strong>.'),
        'deleting with N contexts and 1 child' => __('This category exists in <strong>{{context_count}} contexts</strong> and has <strong>one sub-category</strong>.'),
        'deleting with N languages and N children' => __('This category exists in <strong>{{language_count}} languages</strong> and has <strong>{{children_count}} sub-categories</strong>.'),
        'deleting with N languages and 1 child' => __('This category exists in <strong>{{language_count}} languages</strong> and has <strong>one sub-category</strong>.'),

        # Keep only if the model has the behaviour Tree
        'deleting with 1 child' => __('This category has <strong>1 sub-category</strong>.'),
        'deleting with N children' => __('This category has <strong>{{children_count}} sub-categories</strong>.'),
    ),
    'actions' => array(
        'add' => array(
            'visible' => array(
                'check_permission' => $check_permission_category(true),
            ),
            'disabled' => array(
                'check_permission' => $check_permission_category(),
            ),
        ),
        'edit' => array(
            'disabled' => array(
                'check_permission' => $check_permission_category(),
            ),
        ),
        'visualise' => false,
        'delete' => array(
            'disabled' => array(
                'check_permission' => $check_permission_category(),
            ),
        ),
    ),
);
