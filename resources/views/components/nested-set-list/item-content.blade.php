@if($isReorderable())
<button type="button" x-sortable-handle class="cursor-move text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400">
  <x-heroicon-m-bars-2 class="w-4 h-4" />
</button>
@endif

<div class="flex-1 flex items-center gap-2">
  <span class="text-gray-900 dark:text-gray-100">{{ $item->{$getLabelKey()} }}</span>
</div>

<div class="flex items-center gap-2">
  <button type="button" wire:click="mountFormComponentAction('{{ $getStatePath() }}', 'edit', { id: '{{ $item->id }}' })" class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400">
    <x-heroicon-m-pencil-square class="w-4 h-4" />
  </button>

  @if($isAddable())
  <button type="button" wire:click="mountFormComponentAction('{{ $getStatePath() }}', 'addChild', { parentId: '{{ $item->id }}' })" class="text-primary-600 hover:text-primary-500 dark:text-primary-500 dark:hover:text-primary-400">
    <x-heroicon-m-plus class="w-4 h-4" />
  </button>
  @endif

  @if($isDeletable() && (!$isParent || $item->parent_id !== null))
  <button type="button" wire:click="mountFormComponentAction('{{ $getStatePath() }}', 'delete', { id: '{{ $item->id }}' })" class="text-danger-600 hover:text-danger-500 dark:text-danger-500 dark:hover:text-danger-400">
    <x-heroicon-m-trash class="w-4 h-4" />
  </button>
  @endif
</div>
