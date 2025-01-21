<?php

namespace App\Helpers;

use App\Exports\DatatableExport;
use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Maatwebsite\Excel\Facades\Excel;

class DatatableForResource
{
    private $initialQuery;  // Original Query Builder Instance
    private $query; // Modified Query Builder Instance
    public $sortColumn; // Data Sort Column
    public $order; // Data Sort Order
    public int $limit = 100; // Page Limit
    public int $page = 1; // Page Number
    public $filter; // Page Number
    private $searchColumns = [];
    private $filters = [];
    private $search;
    private $fullTextSearch;
    private $rangeColumn;
    private $rangeStart;
    private $rangeEnd;
    private $exportType = null;
    private $exportHeaders = null;
    private $exportMapping = null;

    /**
     * @throws Exception
     */
    public function __construct($query, array $config = [])
    {
        if (!$query instanceof EloquentBuilder && !$query instanceof QueryBuilder) {
            throw new Exception(
                "Argument 1 passed to App\Classes\Datatable::make() must be an instance
                of Illuminate\Database\Eloquent\Builder or Illuminate\Database\Query\Builder",
                1
            );
        }

        $this->initialQuery = $query;

        $this->query = $query;

        $this->getQueryFilters();

        if (isset($config['search']) && (is_array($config['search']) || is_callable($config['search']))) {
            $this->searchColumns = $config['search'];
        }

        if (isset($config['filters']) && is_array($config['filters'])) {
            $this->filters = $config['filters'];
        }

        if (isset($config['sort']) && is_array($config['sort'])) {
            $sort = $config['sort'];
            $this->sortColumn = $sort['column'] ?? null;
            $this->order = $sort['order'] ?? null;
        }

        if ($this->search && is_string($this->search)) {
            $this->searchData();
        }

        if ($this->filter && is_string($this->filter)) {
            $this->filterData();
        }

        if (array_key_exists('rangeColumn', $config)) {
            $this->rangeColumn = $config['rangeColumn'];
            $this->rangeStart = request()->rangeStart;
            $this->rangeEnd = request()->rangeEnd;

            if ($this->rangeColumn && $this->rangeStart && $this->rangeEnd) {
                $this->setRange();
            }
        }

        if (request()->exportType) {
            $this->exportType = request()->exportType;

            if (array_key_exists('exportHeaders', $config)) {
                $this->exportHeaders = $config['exportHeaders'];
            }

            if (array_key_exists('exportMapping', $config)) {
                $this->exportMapping = $config['exportMapping'];
            }
        }
    }

    /**
     * @throws Exception
     */
    public static function make($query, string $resource, array $config = [])
    {
        if (!$query instanceof EloquentBuilder && !$query instanceof QueryBuilder) {
            throw new Exception(
                "Argument 1 passed to App\Classes\Datatable::make() must be an
                instance of Illuminate\Database\Eloquent\Builder or Illuminate\Database\Query\Builder",
                1
            );
        }

        // Get Class Name
        $className = self::class;

        // Instantiate new Datatable Builder
        $datatable = new $className($query, $config);

        $totalCount = $datatable->countTotal();

        switch ($datatable->exportType) {
            case 'with-filters':
                return $datatable->exportWithFilters($datatable->getDataWithoutLimit());
            case 'all':
                return $datatable->exportPlain($datatable->getDataWithoutLimitAndWithSort());
        }

        $chunk = $datatable->getData();

        $chunk = $resource::collection($chunk);

        $pageCount = $datatable->countPages();

        $chunkCount = count($chunk);

        $data = [
            'data' => $chunk,
            'total_count' => $totalCount,
            'chunk_count' => $chunkCount,
            'page_count' => $pageCount,
            'page' => $datatable->page,
            'limit' => $datatable->limit,
            'order' => $datatable->order,
        ];
        if (config('app.env') === 'local') {
            $data['query'] = $datatable->getDataQuery();
        }

        return json_decode(json_encode($data));
    }

    private function getQuery()
    {
        return (clone $this->query);
    }

    private function getInitialQuery()
    {
        return (clone $this->initialQuery);
    }

    private function countTotal()
    {
        return $this->getQuery()
            ->count();
    }

    private function countData() //@phpstan-ignore-line
    {
        return $this->getQuery()
            ->offset(($this->page - 1) * $this->limit)
            ->limit($this->limit)
            ->count();
    }

    private function getDataQuery()
    {
        $query = $this->getQuery();

        if ($this->fullTextSearch && $this->search && !is_null($this->search) && gettype($this->search) == 'string') {
            $query->offset(0)->limit(1);
        } else {
            $query->offset(($this->page - 1) * $this->limit)
                ->limit($this->limit);
        }

        if ($this->sortColumn !== false && $this->order !== false) {
            if ($this->order == "ASC" || $this->order == "asc") {
                $sorted = $query->oldest($this->sortColumn);
            } else {
                $sorted = $query->latest($this->sortColumn);
            }
        }

        return $query->toSql();
    }

    private function countPages()
    {
        return ceil($this->countTotal() / $this->limit);
    }

    private function getData()
    {
        $query = $this->getQuery();

        if ($this->fullTextSearch && $this->search && is_string($this->search)) {
            $query->offset(0)->limit(1);
        } else {
            $query->offset(($this->page - 1) * $this->limit)
                ->limit($this->limit);
        }

        // Handle Sorting
        if ($this->sortColumn !== false && $this->order !== false) {
            if ($this->order === "ASC" || $this->order === "asc") {
                $sorted = $query->oldest($this->sortColumn);
            } else {
                $sorted = $query->latest($this->sortColumn);
            }
        }

        return $query->get();
    }

    private function getDataWithoutLimit()
    {
        $query = $this->getQuery();

        if ($this->sortColumn !== false && $this->order !== false) {
            if ($this->order === "ASC" || $this->order === "asc") {
                $sorted = $query->oldest($this->sortColumn);
            } else {
                $sorted = $query->latest($this->sortColumn);
            }
        }

        return $query->get();
    }

    private function getDataWithoutLimitAndWithSort()
    {
        $query = $this->getInitialQuery();

        if ($this->sortColumn !== false && $this->order !== false) {
            if ($this->order === "ASC" || $this->order === "asc") {
                $sorted = $query->oldest($this->sortColumn);
            } else {
                $sorted = $query->latest($this->sortColumn);
            }
        }

        return $query->get();
    }

    private function searchData()
    {
        $key = $this->search;

        if (!$key) {
            return false;
        }

        $this->query = $this->query->where(function ($query) {
            $index = 0;

            if (is_callable($this->searchColumns)) {
                call_user_func_array($this->searchColumns, [$query, $this->search]);
            } else {
                foreach ($this->searchColumns as $column) {
                    if ($index > 0) {
                        if ($this->fullTextSearch) {
                            $query->orwhere($column, '=', $this->search);
                        } else {
                            $query->orwhere($column, 'like', "%" . $this->search . "%");
                        }
                    } else {
                        if ($this->fullTextSearch) {
                            $query->where($column, '=', $this->search);
                        } else {
                            $query->where($column, 'like', "%" . $this->search . "%");
                        }
                    }
                    $index++;
                }
            }
        });
    }

    private function filterData()
    {
        $filterName = $this->filter;

        $filter = isset($this->filters[$filterName]) ? $this->filters[$filterName] : null;

        if (!$filter) {
            return false;
        }

        call_user_func_array($filter, [$this->query]);
    }

    public function setRange()
    {
        $start = date('Y-m-d', strtotime($this->rangeStart));
        $end = date('Y-m-d', strtotime($this->rangeEnd));
        $this->query = $this->query->where($this->rangeColumn, '>', $start)->where($this->rangeColumn, '<', $end);
    }

    private function getQueryFilters()
    {
        $this->order = request()->input('order') ?? null;

        $this->page = request()->input('page') ?? 1;

        $this->limit = request()->input('limit') ?? 100;

        $this->search = request()->input('search') ?? null;

        $this->fullTextSearch = request()->input('fullTextSearch') ?? false;

        $this->filter = request()->input('filter') ?? null;
    }

    private function getExportHeaders($data)
    {
        if ($this->exportHeaders) {
            return $this->exportHeaders;
        }

        return count($data) ? array_keys($data[0]->toArray()) : [];
    }

    private function getExportMapping($data)
    {
        if ($this->exportMapping) {
            return $this->exportMapping;
        }

        return function ($data) {
            return array_values($data->toArray());
        };
    }

    public function exportWithFilters($data)
    {
        return Excel::download(new DatatableExport(
            $data,
            $this->getExportHeaders($data),
            $this->getExportMapping($data)
        ), 'export-' . date('Y-m-d') . '.xlsx');
    }

    public function exportPlain($data)
    {
        return Excel::download(new DatatableExport(
            $data,
            $this->getExportHeaders($data),
            $this->getExportMapping($data)
        ), 'export-' . date('Y-m-d') . '.xlsx');
    }
}
