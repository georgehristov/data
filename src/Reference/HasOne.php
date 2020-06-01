<?php

namespace atk4\data\Reference;

use atk4\core\Exception;
use atk4\data\Field;
use atk4\data\Join;
use atk4\data\Model;
use atk4\data\Reference;

/**
 * Reference\HasOne class.
 */
class HasOne extends Reference
{
    /**
     * Field type.
     *
     * Values are: 'string', 'text', 'boolean', 'integer', 'money', 'float',
     *             'date', 'datetime', 'time', 'array', 'object'.
     * Can also be set to unspecified type for your own custom handling.
     *
     * @var string
     */
    public $type;

    /**
     * Is it system field?
     * System fields will be always loaded and saved.
     *
     * @var bool
     */
    public $system = false;

    /**
     * Points to the join if we are part of one.
     *
     * @var Join|null
     */
    protected $join;

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default;

    /**
     * Setting this to true will never actually store
     * the field in the database. It will action as normal,
     * but will be skipped by update/insert.
     *
     * @var bool
     */
    public $never_persist = false;

    /**
     * Is field read only?
     * Field value may not be changed. It'll never be saved.
     * For example, expressions are read only.
     *
     * @var bool
     */
    public $read_only = false;

    /**
     * Defines a label to go along with this field. Use getCaption() which
     * will always return meaningful label (even if caption is null). Set
     * this property to any string.
     *
     * @var string
     */
    public $caption;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * By default hasOne relation ID field should be editable in forms,
     * but not visible in grids. UI should respect these flags.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Is field mandatory? By default fields are not mandatory.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Is field required? By default fields are not required.
     *
     * @var bool|string
     */
    public $required = false;

    /**
     * Should we use typecasting when saving/loading data to/from persistence.
     *
     * Value can be array [$typecast_save_callback, $typecast_load_callback].
     *
     * @var bool|array|null
     */
    public $typecast;

    /**
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * Value can be array [$encode_callback, $decode_callback].
     *
     * @var bool|array|string|null
     */
    public $serialize;

    /**
     * Persisting format for type = 'date', 'datetime', 'time' fields.
     *
     * For example, for date it can be 'Y-m-d', for datetime - 'Y-m-d H:i:s.u' etc.
     *
     * @var string
     */
    public $persist_format;

    /**
     * Persisting timezone for type = 'date', 'datetime', 'time' fields.
     *
     * For example, 'IST', 'UTC', 'Europe/Riga' etc.
     *
     * @var string
     */
    public $persist_timezone = 'UTC';

    /**
     * DateTime class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTime', 'Carbon' etc.
     *
     * @var string
     */
    public $dateTimeClass = 'DateTime';

    /**
     * Timezone class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTimeZone', 'Carbon' etc.
     *
     * @var string
     */
    public $dateTimeZoneClass = 'DateTimeZone';

    /**
     * Reference\HasOne will also add a field corresponding
     * to 'our_field' unless it exists of course.
     *
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();

        if (!$this->our_field) {
            $this->our_field = $this->link;
        }

        if (!$this->owner->hasField($this->our_field)) {
            $this->owner->addField($this->our_field, [
                'type' => $this->type,
                'reference' => $this,
                'system' => $this->system,
                'join' => $this->join,
                'default' => $this->default,
                'never_persist' => $this->never_persist,
                'read_only' => $this->read_only,
                'caption' => $this->caption,
                'ui' => $this->ui,
                'mandatory' => $this->mandatory,
                'required' => $this->required,
                'typecast' => $this->typecast,
                'serialize' => $this->serialize,
                'persist_format' => $this->persist_format,
                'persist_timezone' => $this->persist_timezone,
                'dateTimeClass' => $this->dateTimeClass,
                'dateTimeZoneClass' => $this->dateTimeZoneClass,
            ]);
        }
    }

    /**
     * Returns our field or id field.
     *
     * @throws Exception
     */
    protected function referenceOurValue(): Field
    {
        $this->owner->persistence_data['use_table_prefixes'] = true;

        return $this->owner->getField($this->our_field);
    }

    /**
     * If owner model is loaded, then return referenced model with respective record loaded.
     *
     * If owner model is not loaded, then return referenced model with condition set.
     * This can happen in case of deep traversal $m->ref('Many')->ref('one_id'), for example.
     *
     * @param array $defaults Properties
     *
     * @throws Exception
     * @throws \atk4\data\Exception
     */
    public function ref($defaults = []): Model
    {
        $m = $this->getModel($defaults);

        // add hook to set our_field = null when record of referenced model is deleted
        $m->onHook(Model::HOOK_AFTER_DELETE, function ($m) {
            $this->owner->set($this->our_field, null);
        });

        // if owner model is loaded, then try to load referenced model
        if ($this->their_field) {
            if ($this->owner->get($this->our_field)) {
                $m->tryLoadBy($this->their_field, $this->owner->get($this->our_field));
            }

            $m->onHook(Model::HOOK_AFTER_SAVE, function ($m) {
                $this->owner->set($this->our_field, $m->get($this->their_field));
            });
        } else {
            if ($this->owner->get($this->our_field)) {
                $m->tryLoad($this->owner->get($this->our_field));
            }

            $m->onHook(Model::HOOK_AFTER_SAVE, function ($m) {
                $this->owner->set($this->our_field, $m->id);
            });
        }

        return $m;
    }
}
