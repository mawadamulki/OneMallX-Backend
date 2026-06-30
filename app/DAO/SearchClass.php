<?php

namespace App\DAO;

use App\Models\Product;
use App\Models\Service;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SearchClass implements SearchInterface
{
    public function searchStores(string $query, int $perPage): LengthAwarePaginator
    {
        $builder = Store::query()
            ->visibleToCustomers()
            ->with(['area.floor', 'area.category', 'media' => fn ($q) => $q->orderBy('id')])
            ->withCount('rates')
            ->withAvg('rates', 'score');

        $this->applySearch($builder, 'stores.name, stores.description', ['stores.name', 'stores.description'], $query);

        return $builder
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function searchProducts(string $query, int $perPage, ?int $storeId = null): LengthAwarePaginator
    {
        $builder = Product::query()
            ->where('status', 'active')
            ->whereHas('store', fn ($q) => $q->where('accountStatus', 'active'))
            ->with([
                'store:id,name',
                'media' => fn ($q) => $q->orderBy('id'),
                'categories:id,name,slug',
                'variants' => fn ($q) => $q
                    ->select(['id', 'productID', 'price', 'quantity', 'attributeName', 'isDefault', 'status'])
                    ->where('status', 'active')
                    ->orderByDesc('isDefault'),
            ])
            ->withCount('rates')
            ->withAvg('rates', 'score');

        if ($storeId !== null) {
            $builder->where('storeID', $storeId);
        }

        $this->applySearch(
            $builder,
            'products.name, products.shortDetail, products.detail',
            ['products.name', 'products.shortDetail', 'products.detail'],
            $query,
        );

        return $builder
            ->orderByDesc('isFeatured')
            ->orderByDesc('publishedAt')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function searchServices(string $query, int $perPage): LengthAwarePaginator
    {
        $builder = Service::query()
            ->visibleToCustomers()
            ->with(['area.floor', 'area.category', 'media' => fn ($q) => $q->orderBy('id')])
            ->withCount('rates')
            ->withAvg('rates', 'score');

        $this->applySearch($builder, 'services.name, services.description', ['services.name', 'services.description'], $query);

        return $builder
            ->orderBy('name')
            ->paginate($perPage);
    }

    private function applySearch(Builder $builder, string $fullTextColumns, array $likeColumns, string $query): void
    {
        $query = trim($query);

        if ($query === '') {
            $builder->whereRaw('0 = 1');

            return;
        }

        $booleanTerm = $this->toFullTextBooleanTerm($query);

        if ($booleanTerm !== '') {
            $builder->where(function (Builder $search) use ($fullTextColumns, $booleanTerm, $likeColumns, $query) {
                $search->whereRaw(
                    "MATCH({$fullTextColumns}) AGAINST(? IN BOOLEAN MODE)",
                    [$booleanTerm]
                );

                if (mb_strlen($query) < 3) {
                    $search->orWhere(function (Builder $likeSearch) use ($likeColumns, $query) {
                        foreach ($likeColumns as $column) {
                            $likeSearch->orWhere($column, 'like', '%'.$query.'%');
                        }
                    });
                }
            });

            if (mb_strlen($query) >= 3) {
                $builder->orderByRaw(
                    "MATCH({$fullTextColumns}) AGAINST(? IN BOOLEAN MODE) DESC",
                    [$booleanTerm]
                );
            }

            return;
        }

        $builder->where(function (Builder $likeSearch) use ($likeColumns, $query) {
            foreach ($likeColumns as $column) {
                $likeSearch->orWhere($column, 'like', '%'.$query.'%');
            }
        });
    }

    private function toFullTextBooleanTerm(string $query): string
    {
        $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];

        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]+/u', '', $word) ?? '';

            if ($clean === '') {
                continue;
            }

            $terms[] = '+'.str_replace(['+', '-', '>', '<', '(', ')', '~', '*', '"'], '', $clean).'*';
        }

        return implode(' ', $terms);
    }
}
