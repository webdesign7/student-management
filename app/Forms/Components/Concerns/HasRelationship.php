<?php

namespace App\Forms\Components\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Saade\FilamentAdjacencyList\Forms\Components\Actions\Action;
use Saade\FilamentAdjacencyList\Forms\Components\AdjacencyList;
use Saade\FilamentAdjacencyList\Forms\Components\Component;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

trait HasRelationship
{
  protected string | Closure | null $relationship = null;

  protected ?Collection $cachedExistingRecords = null;

  protected string | Closure | null $orderColumn = null;

  protected ?Closure $modifyRelationshipQueryUsing = null;

  protected ?Closure $mutateRelationshipDataBeforeCreateUsing = null;

  protected ?Closure $mutateRelationshipDataBeforeFillUsing = null;

  protected ?Closure $mutateRelationshipDataBeforeSaveUsing = null;

  protected array | Closure | null $pivotAttributes = null;

  public function relationship(string | Closure | null $name = null, ?Closure $modifyQueryUsing = null): static
  {
    $this->relationship = $name ?? $this->getName();
    $this->modifyRelationshipQueryUsing = $modifyQueryUsing;

    $this->loadStateFromRelationshipsUsing(static function (AdjacencyList $component) {
      $component->clearCachedExistingRecords();

      $component->fillFromRelationship();
    });

    $this->saveRelationshipsUsing(static function (AdjacencyList $component, ?array $state) {
      if (!is_array($state)) {
        $state = [];
      }

      $cachedExistingRecords = $component->getCachedExistingRecords();
      $relationship = $component->getRelationship();
      $childrenKey = $component->getChildrenKey();
      $orderColumn = $component->getOrderColumn();
      $parentModel = $component->getModelInstance();
      $rootSubjectId = null;

      // Store root subject ID if in Department context
      if ($parentModel instanceof \App\Models\Department) {
        $rootSubjectId = $parentModel->root_subject_id;
      }

      // First pass: collect all existing IDs from the state
      $existingIds = [];
      $collectIds = function ($items) use (&$collectIds, &$existingIds, $childrenKey) {
        foreach ($items as $item) {
          if (isset($item['id'])) {
            $existingIds[] = $item['id'];
          }
          if (isset($item[$childrenKey]) && is_array($item[$childrenKey])) {
            $collectIds($item[$childrenKey]);
          }
        }
      };
      $collectIds($state);

      // Delete records that are no longer in the state
      $model = $component->getRelatedModel();
      if ($parentModel instanceof \App\Models\Department) {
        $model::where('id', '!=', $rootSubjectId)
          ->whereNotIn('id', $existingIds)
          ->delete();
      }

      $traverse = function (array $item, string $itemKey, ?Model $parent = null, int $order = 0) use (&$traverse, &$cachedExistingRecords, $childrenKey, $orderColumn, $component, $rootSubjectId, $model): Model {
        // Find existing record by ID first
        $record = null;
        if (isset($item['id'])) {
          $record = $model::find($item['id']);
        }

        if (!$record && isset($item['id'])) {
          $record = $cachedExistingRecords->first(function ($existingRecord) use ($item) {
            return $existingRecord->id == $item['id'];
          });
        }

        // Create new record if not found
        if (!$record) {
          $record = new $model();
        }

        // Fill record data
        $record->fill(array_diff_key($item, [$childrenKey => true]));

        // Handle sorting
        if ($orderColumn) {
          $record->{$orderColumn} = (string) $order;
        }

        // Save the record first
        if (!$record->exists) {
          $record->save();
        }

        // Handle parent relationship using nested set methods
        if ($parent) {
          if ($record->parent_id !== $parent->id) {
            $record->appendToNode($parent)->save();
          }
        } elseif ($rootSubjectId && $record->id !== $rootSubjectId) {
          $rootNode = $model::find($rootSubjectId);
          if ($rootNode && $record->parent_id !== $rootNode->id) {
            $record->appendToNode($rootNode)->save();
          }
        } else {
          if ($record->parent_id !== null) {
            $record->saveAsRoot();
          }
        }

        // Process children
        if ($children = data_get($item, $childrenKey)) {
          $childOrder = 0;
          foreach ($children as $childKey => $child) {
            $traverse($child, $childKey, $record, $childOrder++);
          }
        }

        return $record;
      };

      // Process all records
      foreach ($state as $itemKey => $item) {
        $traverse($item, $itemKey, null, 0);
      }

      // Fix the tree structure
      $model::fixTree();

      // Clear and reload the cache
      $component->clearCachedExistingRecords();
      $component->fillFromRelationship();
    });

    $this->addAction(function (Action $action): void {
      $action->using(function (Component $component, array $data): void {
        $relationship = $component->getRelationship();
        $model = $component->getRelatedModel();
        $pivotData = $component->getPivotAttributes() ?? [];

        if ($relationship instanceof BelongsToMany) {
          $pivotColumns = $relationship->getPivotColumns();

          $pivotData = Arr::only($data, $pivotColumns);
          $data = Arr::except($data, $pivotColumns);
        }

        $data = $component->mutateRelationshipDataBeforeCreate($data);

        if ($translatableContentDriver = $component->getLivewire()->makeFilamentTranslatableContentDriver()) {
          $record = $translatableContentDriver->makeRecord($model, $data);
        } else {
          $record = new $model();
          $record->fill($data);
        }

        if ($orderColumn = $component->getOrderColumn()) {
          $record->{$orderColumn} = $pivotData[$orderColumn] = count($component->getState());
        }

        if ($relationship instanceof BelongsToMany) {
          $record->save();

          $relationship->attach($record, $pivotData);

          $component->cacheRecord($record);

          return;
        }

        $relationship->save($record);

        $component->cacheRecord($record);
      });
    });

    $this->addChildAction(function (Action $action): void {
      $action->using(function (Component $component, ?Model $parentRecord, array $data, array $arguments): void {
        $relationship = $component->getRelationship();
        $model = $component->getRelatedModel();
        $parentModel = $component->getModelInstance();

        // Initialize the record
        $record = new $model();
        $record->fill($data);

        // If this is a Department context
        if ($parentModel instanceof \App\Models\Department) {
          $rootSubjectId = $parentModel->root_subject_id;

          // If no parent record but we have a root subject, use root as parent
          if (!$parentRecord && $rootSubjectId) {
            $parentRecord = $model::find($rootSubjectId);
          }
        }

        // Handle sorting
        if ($orderColumn = $component->getOrderColumn()) {
          $existingCount = 0;
          if ($parentRecord) {
            $existingCount = $parentRecord->children()->count();
          }
          $record->{$orderColumn} = $existingCount;
        }

        // Set parent relationship
        if ($parentRecord) {
          $record->parent_id = $parentRecord->id;
        }

        $record->save();
        $component->cacheRecord($record);
      });
    });

    $this->editAction(function (Action $action): void {
      $action->using(function (Component $component, Model $record, array $data): void {
        $relationship = $component->getRelationship();

        $translatableContentDriver = $component->getLivewire()->makeFilamentTranslatableContentDriver();

        if ($relationship instanceof BelongsToMany) {
          $pivot = $record->{$relationship->getPivotAccessor()};

          $pivotColumns = $relationship->getPivotColumns();
          $pivotData = Arr::only($data, $pivotColumns);

          if (count($pivotColumns)) {
            if ($translatableContentDriver) {
              $translatableContentDriver->updateRecord($pivot, $pivotData);
            } else {
              $pivot->update($pivotData);
            }
          }

          $data = Arr::except($data, $pivotColumns);
        }

        $data = $component->mutateRelationshipDataBeforeSave($data, $record);

        if ($translatableContentDriver) {
          $translatableContentDriver->updateRecord($record, $data);
        } else {
          $record->update($data);
        }
      });
    });

    $this->deleteAction(function (Action $action): void {
      $action->using(function (Component $component, ?Model $record): void {
        // Get the record from cache if not provided
        if (!$record) {
          $cachedExistingRecords = $component->getCachedExistingRecords();
          $itemKey = $component->getState()['record'] ?? null;
          if ($itemKey) {
            $record = $cachedExistingRecords->get($itemKey);
          }

          if (!$record) {
            return;
          }
        }

        $relationship = $component->getRelationship();
        $parentModel = $component->getModelInstance();

        // If this is a Department, prevent deleting the root subject
        if ($parentModel instanceof \App\Models\Department) {
          $rootSubjectId = $parentModel->root_subject_id;
          if ($rootSubjectId && $record->id === $rootSubjectId) {
            return;
          }
        }

        // Delete children recursively
        if (method_exists($record, 'children')) {
          foreach ($record->children()->get() as $child) {
            if (method_exists($child, 'children')) {
              $child->children()->delete();
            }
            $child->delete();
          }
        }

        $record->delete();
        $component->deleteCachedRecord($record);
      });
    });

    $this->dehydrated(false);

    return $this;
  }

  public function fillFromRelationship(): void
  {
    $this->state(
      $this->getStateFromRelatedRecords($this->getCachedExistingRecords()),
    );
  }

  /**
   * @return array<array<string, mixed>>
   */
  protected function getStateFromRelatedRecords(Collection $records): array
  {
    if (! $records->count()) {
      return [];
    }

    return $records
      ->toTree()
      ->mapWithKeys(
        $cb = function (Model $record) use (&$cb): array {
          $childrenKey = $this->getChildrenKey();

          $data = $this->mutateRelationshipDataBeforeFill(
            $this->getLivewire()->makeFilamentTranslatableContentDriver() ?
              $this->getLivewire()->makeFilamentTranslatableContentDriver()->getRecordAttributesToArray($record) :
              $record->attributesToArray()
          );

          $key = md5('record-' . $record->getKey());

          if ($record->children) {
            $data[$childrenKey] = $record->children->mapWithKeys($cb)->toArray();
          } else {
            $data[$childrenKey] = [];
          }

          return [$key => $data];
        }
      )
      ->toArray();
  }

  public function orderColumn(string | Closure | null $column = 'sort'): static
  {
    $this->orderColumn = $column;

    return $this;
  }

  public function getOrderColumn(): ?string
  {
    return $this->evaluate($this->orderColumn);
  }

  public function getRelationship(): HasMany | BelongsToMany | null
  {
    $name = $this->getRelationshipName();

    if (blank($name)) {
      return null;
    }

    if ($model = $this->getModelInstance()) {
      if (! method_exists($model, 'children')) {
        throw new \Exception('The model ' . $model::class . ' must implement a children() relationship method.');
      }
    }

    return $model->{$name}();
  }

  public function getRelationshipName(): ?string
  {
    return $this->evaluate($this->relationship);
  }

  public function cacheRecord(Model $record): void
  {
    $this->cachedExistingRecords?->put(md5('record-' . $record->getKey()), $record);

    $this->fillFromRelationship();
  }

  public function deleteCachedRecord(Model $record): void
  {
    $this->cachedExistingRecords?->forget(md5('record-' . $record->getKey()));

    $this->fillFromRelationship();
  }

  public function getCachedExistingRecords(): Collection
  {
    if ($this->cachedExistingRecords) {
      return $this->cachedExistingRecords;
    }

    $relationship = $this->getRelationship();
    $relationshipQuery = $relationship->getQuery();
    $parentModel = $this->getModelInstance();

    if ($parentModel instanceof \App\Models\Department) {
      $rootSubjectId = $parentModel->root_subject_id;
      if ($rootSubjectId) {
        $relationshipQuery->where(function ($query) use ($rootSubjectId) {
          $query->where('subjects.id', $rootSubjectId)
            ->orWhere(function ($q) use ($rootSubjectId) {
              $q->whereHas('ancestors', function ($aq) use ($rootSubjectId) {
                $aq->where('subjects.id', $rootSubjectId);
              });
            });
        });
      }
    }

    if ($this->modifyRelationshipQueryUsing) {
      $relationshipQuery = $this->evaluate($this->modifyRelationshipQueryUsing, [
        'query' => $relationshipQuery,
      ]) ?? $relationshipQuery;
    }

    if ($orderColumn = $this->getOrderColumn()) {
      $relationshipQuery->orderBy($orderColumn, 'asc');
    }

    $this->cachedExistingRecords = $relationshipQuery->with('children')->get();

    return $this->cachedExistingRecords;
  }

  public function clearCachedExistingRecords(): void
  {
    $this->cachedExistingRecords = null;
  }

  public function getRelatedModel(): ?string
  {
    return ($model = $this->getRelationship()?->getModel()) ? $model::class : null;
  }

  public function mutateRelationshipDataBeforeCreateUsing(?Closure $callback): static
  {
    $this->mutateRelationshipDataBeforeCreateUsing = $callback;

    return $this;
  }

  /**
   * @param  array<array<string, mixed>>  $data
   * @return array<array<string, mixed>>
   */
  public function mutateRelationshipDataBeforeCreate(array $data): array
  {
    if ($this->mutateRelationshipDataBeforeCreateUsing instanceof Closure) {
      $data = $this->evaluate($this->mutateRelationshipDataBeforeCreateUsing, [
        'data' => $data,
      ]);
    }

    return $data;
  }

  /**
   * @param  array<array<string, mixed>>  $data
   * @return array<array<string, mixed>>
   */
  public function mutateRelationshipDataBeforeFill(array $data): array
  {
    if ($this->mutateRelationshipDataBeforeFillUsing instanceof Closure) {
      $data = $this->evaluate($this->mutateRelationshipDataBeforeFillUsing, [
        'data' => $data,
      ]);
    }

    return $data;
  }

  public function mutateRelationshipDataBeforeFillUsing(?Closure $callback): static
  {
    $this->mutateRelationshipDataBeforeFillUsing = $callback;

    return $this;
  }

  public function mutateRelationshipDataBeforeSaveUsing(?Closure $callback): static
  {
    $this->mutateRelationshipDataBeforeSaveUsing = $callback;

    return $this;
  }

  /**
   * @param  array<array<string, mixed>>  $data
   * @return array<array<string, mixed>>
   */
  public function mutateRelationshipDataBeforeSave(array $data, Model $record): array
  {
    if ($this->mutateRelationshipDataBeforeSaveUsing instanceof Closure) {
      $data = $this->evaluate(
        $this->mutateRelationshipDataBeforeSaveUsing,
        namedInjections: [
          'data' => $data,
          'record' => $record,
        ],
        typedInjections: [
          Model::class => $record,
          $record::class => $record,
        ],
      );
    }

    return $data;
  }

  public function pivotAttributes(array | Closure | null $pivotAttributes): static
  {
    $this->pivotAttributes = $pivotAttributes;

    return $this;
  }

  public function getPivotAttributes(): array
  {
    return $this->evaluate($this->pivotAttributes) ?? [];
  }
}
