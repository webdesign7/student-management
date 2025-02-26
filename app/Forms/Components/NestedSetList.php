<?php

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Livewire\Component as Livewire;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Forms;
use Filament\Forms\Components\Hidden;

class NestedSetList extends Field
{
  protected string $view = 'components.nested-set-list';
  protected string $relationship;
  protected string $labelKey = 'name';
  protected string $childrenKey = 'children';
  protected ?string $orderColumn = 'sort';
  protected ?Collection $records = null;
  protected bool $isReorderable = true;
  protected bool $isDeletable = true;
  protected bool $isAddable = true;

  public function relationship(string $name): static
  {
    $this->relationship = $name;

    $this->loadStateFromRelationshipsUsing(function (NestedSetList $component) {
      $component->fillFromRelationship();
    });

    $this->dehydrated(false);

    return $this;
  }

  public function labelKey(string $key): static
  {
    $this->labelKey = $key;
    return $this;
  }

  public function childrenKey(string $key): static
  {
    $this->childrenKey = $key;
    return $this;
  }

  public function orderColumn(string $column): static
  {
    $this->orderColumn = $column;
    return $this;
  }

  public function getTheModel(): mixed
  {
    return $this->getRecord() ?? $this->getContainer()->getModel();
  }

  public function getRelationship(): HasMany|BelongsToMany|null
  {
    if (!$model = $this->getTheModel()) {
      return null;
    }

    return $model->{$this->relationship}();
  }

  public function getRecords(): Collection
  {
    if ($this->records === null) {
      $relationship = $this->getRelationship();

      if (!$relationship) {
        return collect();
      }

      $this->records = $relationship
        ->with('children')
        ->orderBy('sort')
        ->get();
    }

    return $this->records;
  }

  public function fillFromRelationship(): void
  {
    $this->state($this->getStateFromRecords());
  }

  protected function getStateFromRecords(): array
  {
    $records = $this->getRecords();

    if (!$records->count()) {
      return [];
    }

    return $records->map(function ($record) {
      return [
        'id' => $record->id,
        $this->labelKey => $record->{$this->labelKey},
        $this->childrenKey => $this->mapChildren($record->{$this->childrenKey}),
      ];
    })->toArray();
  }

  protected function mapChildren(Collection $children): array
  {
    return $children->map(function ($child) {
      return [
        'id' => $child->id,
        $this->labelKey => $child->{$this->labelKey},
        $this->childrenKey => $this->mapChildren($child->{$this->childrenKey}),
      ];
    })->toArray();
  }

  public function getLabelKey(): string
  {
    return $this->labelKey;
  }

  public function getChildrenKey(): string
  {
    return $this->childrenKey;
  }

  public function reorderable(bool $condition = true): static
  {
    $this->isReorderable = $condition;
    return $this;
  }

  public function deletable(bool $condition = true): static
  {
    $this->isDeletable = $condition;
    return $this;
  }

  public function addable(bool $condition = true): static
  {
    $this->isAddable = $condition;
    return $this;
  }

  public function isReorderable(): bool
  {
    return $this->isReorderable;
  }

  public function isDeletable(): bool
  {
    return $this->isDeletable;
  }

  public function isAddable(): bool
  {
    return $this->isAddable;
  }

  public function reorder(array $items): void
  {
    $model = $this->getRelationship()->getModel();
    $orderColumn = $this->orderColumn;

    collect($items)->each(function ($item, $index) use ($model, $orderColumn) {
      $model->where('id', $item['id'])->update([
        $orderColumn => $index,
        'parent_id' => $item['parent_id'] ?? null,
      ]);
    });

    // Update nested set columns
    $model->fixTree();

    $this->records = null; // Clear cache
  }

  protected function setUp(): void
  {
    parent::setUp();

    $this->registerActions([
      Action::make('addChild')
        ->form([
          TextInput::make('name')
            ->label('Name')
            ->required(),
        ])
        ->modalHeading('Add New Item')
        ->modalSubmitActionLabel('Create')
        ->action(function (array $data, array $arguments): void {
          $this->mountedActionAddChild($arguments['parentId'], $data['name']);
        }),

      Action::make('edit')
        ->form([
          TextInput::make('name')
            ->label('Name')
            ->required(),
        ])
        ->modalHeading('Edit Item')
        ->modalSubmitActionLabel('Save')
        ->mountUsing(function (array $arguments) {
          $model = $this->getRelationship()->getModel();
          $item = $model->find($arguments['id']);

          if (!$item) {
            return [];
          }

          return [
            'name' => $item->{$this->getLabelKey()},
          ];
        })
        ->action(function (array $data, array $arguments): void {
          $model = $this->getRelationship()->getModel();
          $model->find($arguments['id'])->update([
            $this->getLabelKey() => $data['name'],
          ]);
          $this->clearRecordsCache();
        }),

      Action::make('delete')
        ->requiresConfirmation()
        ->modalHeading('Delete Item')
        ->action(function (array $arguments): void {
          $this->mountedActionDelete($arguments['id']);
        }),

      Action::make('reorder')
        ->action(function (array $arguments): void {
          $this->mountedActionReorder($arguments['items']);
        }),
    ]);
  }

  protected function getAlpineData(): array
  {
    return array_merge(parent::getAlpineData(), [
      'sortable' => [
        'options' => [
          'group' => $this->getId(),
          'animation' => 150,
          'ghostClass' => 'opacity-50',
        ],
      ],
    ]);
  }

  public function mountedActionAddChild(string $parentId, string $name): void
  {
    $model = $this->getRelationship()->getModel();
    $model->create([
      'parent_id' => $parentId,
      'name' => $name,
      'sort' => $model->where('parent_id', $parentId)->max('sort') + 1,
    ]);

    $this->clearRecordsCache();
  }

  public function mountedActionDelete(string $id): void
  {
    $model = $this->getRelationship()->getModel();
    $model->find($id)->delete();

    $this->clearRecordsCache();
  }

  public function mountedActionReorder(array $items): void
  {
    $model = $this->getRelationship()->getModel();
    $orderColumn = $this->orderColumn;

    foreach ($items as $item) {
      $model->where('id', $item['value'])->update([
        $orderColumn => $item['order'],
        'parent_id' => $item['parent'] ?? null,
      ]);
    }

    $this->clearRecordsCache();
  }

  public function clearRecordsCache(): void
  {
    $this->records = null;
  }
}
