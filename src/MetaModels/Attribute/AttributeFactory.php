<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package    MetaModels
 * @subpackage Core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\Attribute;

use MetaModels\Attribute\Events\CollectMetaModelAttributeInformationEvent;
use MetaModels\Attribute\Events\CreateAttributeEvent;
use MetaModels\Attribute\Events\CreateAttributeFactoryEvent;
use MetaModels\IMetaModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * This is the implementation of the Field factory to query instances of fields.
 *
 * Usually this is only used internally by {@link MetaModels\Factory}
 *
 * @package    MetaModels
 * @subpackage Core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class AttributeFactory implements IAttributeFactory
{
    /**
     * The event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * The registered type factories.
     *
     * @var IAttributeTypeFactory[]
     */
    protected $typeFactories = array();

    /**
     * Create a new instance.
     *
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher to use.
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->setEventDispatcher($eventDispatcher);

        $eventDispatcher->dispatch(CreateAttributeFactoryEvent::NAME, new CreateAttributeFactoryEvent($this));
    }

    /**
     * Retrieve the event dispatcher.
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Set the event dispatcher.
     *
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher to set.
     *
     * @return Factory
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * Create an attribute instance from an information array.
     *
     * @param array      $information The attribute information.
     *
     * @param IMetaModel $metaModel   The MetaModel instance for which the attribute shall be created.
     *
     * @return IAttribute|null
     */
    public function createAttribute($information, $metaModel)
    {
        $event = new CreateAttributeEvent($information, $metaModel);
        $this->getEventDispatcher()->dispatch(CreateAttributeEvent::NAME, $event);

        if ($event->getAttribute()) {
            return $event->getAttribute();
        }

        $factory = $this->getTypeFactory($information['type']);

        if (!$factory) {
            return null;
        }

        return $factory->createInstance($information, $metaModel);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException When the type is already registered.
     */
    public function addTypeFactory(IAttributeTypeFactory $typeFactory)
    {
        $typeName = $typeFactory->getTypeName();
        if (isset($this->typeFactories[$typeName])) {
            throw new \RuntimeException('Attribute type ' . $typeName . ' is already registered.');
        }

        $this->typeFactories[$typeName] = $typeFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeFactory($typeFactory)
    {
        return isset($this->typeFactories[(string) $typeFactory]) ? $this->typeFactories[(string) $typeFactory] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeTypeMatchesFlags($name, $flags)
    {
        $factory = $this->getTypeFactory($name);

        // Shortcut, if all are valid, return all. :)
        if ($flags === self::FLAG_ALL) {
            return true;
        }

        return (($flags & self::FLAG_INCLUDE_TRANSLATED) && $factory->isTranslatedType())
            || (($flags & self::FLAG_INCLUDE_SIMPLE) && $factory->isSimpleType())
            || (($flags & self::FLAG_INCLUDE_COMPLEX) && $factory->isComplexType());
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeNames($flags = false)
    {
        if ($flags === false) {
            $flags = self::FLAG_ALL;
        }

        $result = array();
        foreach (array_keys($this->typeFactories) as $name) {
            if (!$this->attributeTypeMatchesFlags($name, $flags)) {
                continue;
            }

            $result[] = $name;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function collectAttributeInformation(IMetaModel $metaModel)
    {
        $event = new CollectMetaModelAttributeInformationEvent($metaModel);

        $this->getEventDispatcher()->dispatch($event::NAME, $event);

        return $event->getAttributeInformation();
    }

    /**
     * {@inheritdoc}
     */
    public function createAttributesForMetaModel($metaModel)
    {
        $attributes = array();
        foreach ($this->collectAttributeInformation($metaModel) as $information) {
            $attribute = $this->createAttribute($information, $metaModel);
            if ($attribute) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getIconForType($type)
    {
        return isset($this->typeFactories[(string) $type]) ? $this->typeFactories[(string) $type]->getTypeIcon() : null;
    }
}
