<?php

namespace GetCandy\Api\Search\Services;

use GetCandy\Api\Scaffold\BaseService;
use Elastica\ResultSet;
use GetCandy\Http\Transformers\Fractal\Products\ProductTransformer;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use GetCandy\Http\Transformers\Fractal\Categories\CategoryTransformer;

class SearchService
{
    protected $types = [
        'product' => ProductTransformer::class,
        'category' => CategoryTransformer::class
    ];

    /**
     * Gets the search results from the result set
     *
     * @param ResultSet $results
     * @param string $type
     * @param integer $page
     * @param integer $perpage
     * @param mixed $includes
     * 
     * @return array
     */
    public function getResults(ResultSet $results, $type, $includes = null)
    {
        $ids = [];

        if ($includes) {
            app()->fractal->parseIncludes($includes);
        }
        
        if ($results->count()) {
            foreach ($results as $r) {
                $ids[] = $r->getSource()['id'];
            }
            $collection = app('api')->{str_plural($type)}()->getSearchedIds($ids);
        } else {
            $collection = collect();
        }
        
        $transformer = new $this->types[$type];
        $resource = new Collection($collection, $transformer);


        $resource->setMeta([
            'pagination' => $this->getPagination($results),
            'aggregation' => $this->getSearchAggregator($results),
            'suggestions' => $this->getSuggestions($results)
        ]);
        

        return app()->fractal->createData($resource)->toArray();
    }

    /**
     * Get the pagination for the results
     *
     * @param array $results
     * 
     * @return array
     */
    protected function getPagination($results)
    {
        $query = $results->getQuery();
        $page = $query->getParam('from') / $query->getParam('size');

        $pagination = [
            'total' => $results->getTotalHits(),
            'count' => $results->count(),
            'per_page' => $query->getParam('size'),
            'current_page' => $page <= 1 ? 1 : $page,
            'total_pages' => floor($results->getTotalHits() / $query->getParam('size'))
        ];
        
        return $pagination;
    }

    /**
     * Get the search suggestions
     *
     * @param ResultSet $results
     * 
     * @return array
     */
    public function getSuggestions($results)
    {
        $suggestions = [];
        
        foreach ($results->getSuggests() as $field => $item) {
            foreach ($item as $suggestion) {
                if (count($suggestion['options'])) {
                    foreach ($suggestion['options'] as $option) {
                        $suggestions[$field][] = $option;
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Gets the aggregation fields for the results
     *
     * @param array $results
     * 
     * @return void
     */
    protected function getSearchAggregator($results)
    {
        if (!$results->hasAggregations()) {
            return [];
        }

        $aggs = $results->getAggregations();

        $results = [];

        $selected = [];
        $all = [];

        foreach ($aggs as $handle => $agg) {
            if ($handle == 'categories_after') {
                foreach ($agg['categories_after_filter']['categories_post_inner']['buckets'] as $bucket) {
                    $selected[] = $bucket['key'];
                }
            } if ($handle == 'categories_before') {
                foreach ($agg['categories_before_inner']['buckets'] as $bucket) {
                    $all[] = $bucket['key'];
                }
            }
        }

        $selected = collect($selected);

        $models = app('api')->categories()->getSearchedIds($all);

        foreach ($models as $category) {
            $category->aggregate_selected = $selected->contains($category->encodedId());
        }

        $resource = new Collection($models, new CategoryTransformer);

        $results['categories'] = app()->fractal->createData($resource)->toArray();

        return $results;
    }
}
