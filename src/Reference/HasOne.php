<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

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
    public $type = null;

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
    protected $join = null;

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default = null;

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
    public $caption = null;

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
     * Array with Persistence settings like format, timezone etc.
     * It's job of Persistence to take these settings into account if needed.
     *
     * @var array
     */
    public $persistence = [];

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
     * @var null|bool|array
     */
    public $typecast = null;

    /**
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * Value can be array [$encode_callback, $decode_callback].
     *
     * @var null|bool|array|string
     */
    public $serialize = null;

    /**
     * Reference_One will also add a field corresponding
     * to 'our_field' unless it exists of course.
     *
     * @throws Exception
     */
    public function init()
    {
        parent::init();

        if (!$this->our_field) {
            $this->our_field = $this->link;
        }

        if (!$this->owner->hasField($this->our_field)) {
            $this->owner->addField($this->our_field, [
                'type'              => $this->type,
                'reference'         => $this,
                'system'            => $this->system,
                'join'              => $this->join,
                'default'           => $this->default,
                'never_persist'     => $this->never_persist,
                'read_only'         => $this->read_only,
                'caption'           => $this->caption,
                'ui'                => $this->ui, // UI settings
                'persistence'       => $this->persistence, // Persistence settings
                'mandatory'         => $this->mandatory,
                'required'          => $this->required,
                'typecast'          => $this->typecast,
                'serialize'         => $this->serialize,
                //'dateTimeClass'     => $this->dateTimeClass,      // @TODO these should be passed somehow. field->getSeed() needed here
                //'dateTimeZoneClass' => $this->dateTimeZoneClass,
            ]);
        }
    }

    /**
     * Returns our field or id field.
     *
     * @throws Exception
     *
     * @return Field
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
     *
     * @return Model
     */
    public function ref($defaults = []): Model
    {
        $m = $this->getModel($defaults);

        // add hook to set our_field = null when record of referenced model is deleted
        $m->onHook('afterDelete', function ($m) {
            $this->owner[$this->our_field] = null;
        });

        // if owner model is loaded, then try to load referenced model
        if ($this->their_field) {
            if ($this->owner[$this->our_field]) {
                $m->tryLoadBy($this->their_field, $this->owner[$this->our_field]);
            }

            $m->onHook('afterSave', function ($m) {
                $this->owner[$this->our_field] = $m[$this->their_field];
            });
        } else {
            if ($this->owner[$this->our_field]) {
                $m->tryLoad($this->owner[$this->our_field]);
            }

            $m->onHook('afterSave', function ($m) {
                $this->owner[$this->our_field] = $m->id;
            });
        }

        return $m;
    }
}
