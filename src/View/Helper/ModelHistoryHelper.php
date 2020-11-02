<?php
declare(strict_types = 1);
namespace ModelHistory\View\Helper;

use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\View\Helper;
use ModelHistory\Model\Entity\ModelHistory;

/**
 * ContactPersons helper
 */
class ModelHistoryHelper extends Helper
{

    /**
     * List of helpers used by this helper
     *
     * @var array
     */
    public $helpers = ['Html'];

    /**
     * Render the model history area where needed
     *
     * @param \Cake\Datasource\EntityInterface $entity One historizable entity
     * @param array $options options array
     */
    public function modelHistoryArea(EntityInterface $entity, array $options = []): string
    {
        $options = Hash::merge([
            'showCommentBox' => false,
            'showFilterBox' => false,
            'columnClass' => 'col-md-12',
            'includeAssociated' => false
        ], $options);

        $page = 1;
        $limit = TableRegistry::get($entity->source())->getEntriesLimit();

        $modelHistory = TableRegistry::get('ModelHistory.ModelHistory')->getModelHistory($entity->source(), $entity->id, $limit, $page, [], $options);

        $entries = TableRegistry::get('ModelHistory.ModelHistory')->getModelHistoryCount($entity->source(), $entity->id, [], $options);
        $showNextEntriesButton = $entries > 0 && $limit * $page < $entries;
        $showPrevEntriesButton = $page > 1;

        $contexts = [];
        if (method_exists($entity, 'getContexts')) {
            $contexts = $entity::getContexts();
        }

        return $this->_View->element('ModelHistory.model_history_area', [
            'modelHistory' => $modelHistory,
            'showNextEntriesButton' => $showNextEntriesButton,
            'showPrevEntriesButton' => $showPrevEntriesButton,
            'page' => $page,
            'model' => $entity->source(),
            'foreignKey' => $entity->id,
            'limit' => $limit,
            'searchableFields' => TableRegistry::get($entity->source())->getTranslatedFields(),
            'showCommentBox' => $options['showCommentBox'],
            'showFilterBox' => $options['showFilterBox'],
            'columnClass' => $options['columnClass'],
            'includeAssociated' => $options['includeAssociated'],
            'contexts' => $contexts
        ]);
    }

    /**
     * Convert action to bootstrap class
     *
     * @param  string  $action  History Action
     * @return string
     */
    public function actionClass(string $action): string
    {
        switch ($action) {
            case ModelHistory::ACTION_CREATE:
                $class = 'success';
                break;
            case ModelHistory::ACTION_DELETE:
                $class = 'danger';
                break;
            case ModelHistory::ACTION_COMMENT:
                $class = 'active';
                break;
            case ModelHistory::ACTION_UPDATE:
            default:
                $class = 'info';
                break;
        }

        return $class;
    }

    /**
     * Returns the text displayed in the widget
     *
     * @param ModelHistory $history one ModelHistory entity
     * @return string
     */
    public function historyText(ModelHistory $history): string
    {
        $action = '';
        switch ($history->action) {
            case ModelHistory::ACTION_CREATE:
                $action = __d('model_history', 'created');
                break;
            case ModelHistory::ACTION_UPDATE:
                $action = __d('model_history', 'updated');
                break;
            case ModelHistory::ACTION_DELETE:
                $action = __d('model_history', 'deleted');
                break;
            case ModelHistory::ACTION_COMMENT:
                $action = __d('model_history', 'commented');
                break;
            default:
        }
        if (empty($history->user_id)) {
            $username = 'Anonymous';
        } else {
            $userNameFields = TableRegistry::get($history->model)->getUserNameFields(true);
            $firstname = $history->user->{$userNameFields['firstname']};
            $lastname = $history->user->{$userNameFields['lastname']};
            $username = $firstname . ' ' . $lastname;
        }

        return ucfirst($action) . ' ' . __d('model_history', 'by') . ' ' . $username;
    }

    /**
     * Returns the badge displayed in the widget
     *
     * @param ModelHistory $history one ModelHistory entity
     * @return string
     */
    public function historyBadge(ModelHistory $history): string
    {
        $action = '';
        switch ($history->action) {
            case ModelHistory::ACTION_UPDATE:
                $icon = 'refresh';
                break;
            case ModelHistory::ACTION_DELETE:
                $icon = 'minus-circle';
                break;
            case ModelHistory::ACTION_COMMENT:
                $icon = 'comments';
                break;
            default:
            case ModelHistory::ACTION_CREATE:
                $icon = 'plus-circle';
                break;
        }

        return '<i class="fa fa-' . $icon . '"></i>';
    }

    /**
     * Retrieve field names as localized, comma seperated string.
     *
     * @param  ModelHistory  $historyEntry  A History entry
     * @return string
     */
    public function getLocalizedFieldnames(ModelHistory $historyEntry): string
    {
        $fields = join(', ', array_map(function ($value) use ($historyEntry) {
            if (!is_string($value)) {
                return $value;
            }

            // Get pre configured translations and return it if found
            $fields = TableRegistry::get($historyEntry->model)->getFields();

            if (isset($fields[$value]['translation'])) {
                if (is_callable($fields[$value]['translation'])) {
                    return $fields[$value]['translation']();
                }

                return $fields[$value]['translation'];
            }

            // Try to get the generic model.field translation string
            $localeSlug = strtolower(Inflector::singularize(Inflector::delimit($historyEntry->model))) . '.' . strtolower($value);
            $translatedString = __($localeSlug);

            // Return original value when no translation was made
            if ($localeSlug == $translatedString) {
                return $value;
            }

            return $translatedString;
        }, array_keys($historyEntry->data)));

        return $fields;
    }

    /**
     * Retrieve localized slug, when translation is available in type descriptions.
     *
     * @param  ModelHistory  $historyEntry  HistoryEntry entity to get data from
     * @return string
     */
    public function getLocalizedSlug(ModelHistory $historyEntry): string
    {
        $slug = $historyEntry->context_slug;
        if (!empty($historyEntry->context) && !empty($historyEntry->context['namespace'])) {
            $class = new $historyEntry->context['namespace'];
            if (method_exists($class, 'typeDescriptions')) {
                $typeDescriptions = $class::typeDescriptions();
                if (isset($typeDescriptions[$slug])) {
                    $slug = $typeDescriptions[$slug];
                }
            }
        }

        return $slug;
    }
}
