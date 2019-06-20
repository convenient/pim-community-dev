<?php

namespace Specification\Akeneo\Pim\Enrichment\Bundle\EventSubscriber;

use Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent;
use Akeneo\Tool\Component\StorageUtils\Indexer\BulkIndexerInterface;
use Akeneo\Tool\Component\StorageUtils\Indexer\IndexerInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use PhpSpec\ObjectBehavior;
use Akeneo\Pim\Enrichment\Bundle\EventSubscriber\IndexProductsSubscriber;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\GenericEvent;

class IndexProductsSubscriberSpec extends ObjectBehavior
{
    function let(IndexerInterface $indexer, BulkIndexerInterface $bulkIndexer, RemoverInterface $remover)
    {
        $this->beConstructedWith($indexer, $bulkIndexer, $remover);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(IndexProductsSubscriber::class);
    }

    function it_subscribes_to_events()
    {
        $this->getSubscribedEvents()->shouldReturn([
            StorageEvents::POST_SAVE     => ['indexProduct', 300],
            StorageEvents::POST_SAVE_ALL => ['bulkIndexProducts', 300],
            StorageEvents::PRE_REMOVE    => ['deleteProduct', 300],
        ]);
    }

    function it_indexes_a_single_product($indexer, GenericEvent $event, ProductInterface $product)
    {
        $event->getSubject()->willReturn($product);
        $event->hasArgument('unitary')->willReturn(true);
        $event->getArgument('unitary')->willReturn(true);
        $event->hasArgument('products_to_index')->willReturn(true);
        $event->getArgument('products_to_index')->willReturn(['identifier']);

        $product->getIdentifier()->willReturn('identifier');

        $indexer->index($product)->shouldBeCalled();

        $this->indexProduct($event);
    }

    function it_does_not_index($indexer, GenericEvent $event, ProductInterface $product)
    {
        $event->getSubject()->willReturn($product);
        $event->hasArgument('unitary')->willReturn(true);
        $event->getArgument('unitary')->willReturn(true);
        $event->hasArgument('products_to_index')->willReturn(true);
        $event->getArgument('products_to_index')->willReturn([]);

        $product->getIdentifier()->willReturn('identifier');

        $indexer->index($product)->shouldNotBeCalled();

        $this->indexProduct($event);
    }

    function it_bulk_indexes_products(
        $bulkIndexer,
        GenericEvent $event,
        ProductInterface $product1,
        ProductInterface $product2
    ) {
        $event->getSubject()->willReturn([$product1, $product2]);
        $event->hasArgument('products_to_index')->willReturn(true);
        $event->getArgument('products_to_index')->willReturn(['identifier1', 'identifier2']);

        $product1->getIdentifier()->willReturn('identifier1');
        $product2->getIdentifier()->willReturn('identifier2');

        $bulkIndexer->indexAll([$product1, $product2])->shouldBeCalled();

        $this->bulkIndexProducts($event);
    }

    function it_delete_product_from_elasticsearch_index($remover, RemoveEvent $event, ProductInterface $product)
    {
        $event->getSubjectId()->willReturn(40);
        $event->getSubject()->willReturn($product);

        $remover->remove(40)->shouldBeCalled();

        $this->deleteProduct($event)->shouldReturn(null);
    }

    function it_does_not_index_a_non_unitary_save_of_a_product(
        $indexer,
        GenericEvent $event,
        ProductInterface $product
    ) {
        $event->getSubject()->willReturn($product);
        $event->hasArgument('unitary')->willReturn(true);
        $event->getArgument('unitary')->willReturn(false);

        $indexer->index(Argument::any())->shouldNotBeCalled();

        $this->indexProduct($event);
    }

    function it_does_not_index_a_non_unitary_save_of_a_product_bis(
        $indexer,
        GenericEvent $event,
        ProductInterface $product
    ) {
        $event->getSubject()->willReturn($product);
        $event->hasArgument('unitary')->willReturn(false);

        $indexer->index(Argument::any())->shouldNotBeCalled();

        $this->indexProduct($event);
    }

    function it_does_not_index_a_non_product_entity($indexer, GenericEvent $event, \stdClass $subject)
    {
        $event->getSubject()->willReturn($subject);
        $indexer->index(Argument::cetera())->shouldNotBeCalled();

        $this->indexProduct($event);
    }

    function it_does_not_bulk_index_non_product_entities(
        $bulkIndexer,
        GenericEvent $event,
        \stdClass $subject1
    ) {
        $event->getSubject()->willReturn([$subject1]);

        $bulkIndexer->indexAll(Argument::any())->shouldNotBeCalled();

        $this->bulkIndexProducts($event);
    }

    function it_does_not_bulk_index_non_collections($bulkIndexer, GenericEvent $event, \stdClass $subject1)
    {
        $event->getSubject()->willReturn($subject1);

        $bulkIndexer->indexAll(Argument::any())->shouldNotBeCalled();

        $this->bulkIndexProducts($event);
    }

    function it_does_not_delete_non_product_entity_from_elasticsearch($remover, RemoveEvent $event, \stdClass $subject)
    {
        $event->getSubject()->willReturn($subject);

        $remover->remove(40)->shouldNotBeCalled();

        $this->deleteProduct($event)->shouldReturn(null);
    }
}
