<?php
/**
 * Elasticsearch PHP client
 *
 * @link      https://github.com/elastic/elasticsearch-php/
 * @copyright Copyright (c) Elasticsearch B.V (https://www.elastic.co)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license   https://www.gnu.org/licenses/lgpl-2.1.html GNU Lesser General Public License, Version 2.1 
 * 
 * Licensed to Elasticsearch B.V under one or more agreements.
 * Elasticsearch B.V licenses this file to you under the Apache 2.0 License or
 * the GNU Lesser General Public License, Version 2.1, at your option.
 * See the LICENSE file in the project root for more information.
 */


declare(strict_types = 1);

namespace Elasticsearch\Helper\Iterators;

use Iterator;

/**
 * Class SearchHitIterator
 *
 * @see      Iterator
 */
class SearchHitIterator implements Iterator, \Countable
{

    /**
     * @var SearchResponseIterator
     */
    private $search_responses;

    /**
     * @var int
     */
    protected $current_key;

    /**
     * @var int
     */
    protected $current_hit_index;

    /**
     * @var array|null
     */
    protected $current_hit_data;

    /**
     * @var int
     */
    protected $count;

    /**
     * Constructor
     *
     * @param SearchResponseIterator $search_responses
     */
    public function __construct(SearchResponseIterator $search_responses)
    {
        $this->search_responses = $search_responses;
    }

    /**
     * Rewinds the internal SearchResponseIterator and itself
     *
     * @return void
     * @see    Iterator::rewind()
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->current_key = 0;
        $this->search_responses->rewind();

        // The first page may be empty. In that case, the next page is fetched.
        $current_page = $this->search_responses->current();
        if ($this->search_responses->valid() && empty($current_page['hits']['hits'])) {
            $this->search_responses->next();
        }

        $this->count = 0;
        if (isset($current_page['hits']) && isset($current_page['hits']['total'])) {
            $this->count = $current_page['hits']['total'];
        }

        $this->readPageData();
    }

    /**
     * Advances pointer of the current hit to the next one in the current page. If there
     * isn't a next hit in the current page, then it advances the current page and moves the
     * pointer to the first hit in the page.
     *
     * @return void
     * @see    Iterator::next()
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->current_key++;
        $this->current_hit_index++;
        $current_page = $this->search_responses->current();
        if (isset($current_page['hits']['hits'][$this->current_hit_index])) {
            $this->current_hit_data = $current_page['hits']['hits'][$this->current_hit_index];
        } else {
            $this->search_responses->next();
            $this->readPageData();
        }
    }

    /**
     * Returns a boolean indicating whether or not the current pointer has valid data
     *
     * @return bool
     * @see    Iterator::valid()
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return is_array($this->current_hit_data);
    }

    /**
     * Returns the current hit
     *
     * @return array
     * @see    Iterator::current()
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->current_hit_data;
    }

    /**
     * Returns the current hit index. The hit index spans all pages.
     *
     * @return int
     * @see    Iterator::key()
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->current_key;
    }

    /**
     * Advances the internal SearchResponseIterator and resets the current_hit_index to 0
     *
     * @internal
     */
    private function readPageData()
    {
        if ($this->search_responses->valid()) {
            $current_page = $this->search_responses->current();
            $this->current_hit_index = 0;
            $this->current_hit_data = $current_page['hits']['hits'][$this->current_hit_index];
        } else {
            $this->current_hit_data = null;
        }
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        if ($this->count === null) {
            $this->rewind();
        }

        return $this->count;
    }
}
