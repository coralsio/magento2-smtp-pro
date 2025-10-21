<?php
declare(strict_types=1);

namespace Corals\SMTP\Ui\Component\Listing\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Corals\SMTP\Model\ResourceModel\Email\Log\CollectionFactory;

class Logs extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }
        
        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => array_values($this->getCollection()->toArray()['items'])
        ];
    }
}