<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
  <div x-data="{ 
        reorderItems(items) {
            const reorderedItems = Array.from(items).map((item, index) => ({
                value: item.getAttribute('data-id'),
                order: index,
                parent: item.closest('[data-parent-id]')?.dataset.parentId
            }));
            $wire.mountFormComponentAction('{{ $getStatePath() }}', 'reorder', { items: reorderedItems })
        }
    }" class="space-y-4">
    <div class="flex flex-col gap-4">
      @foreach($getRecords() as $item)
      <div class="space-y-4">
        {{-- Parent Item (Not Sortable) --}}
        <div class="flex items-center gap-2 p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
          @include('components.nested-set-list.item-content', [
          'item' => $item,
          'isParent' => true
          ])
        </div>

        {{-- First Level Children (Sortable) --}}
        @if($item->{$getChildrenKey()}->count())
        <div class="pl-12 border-l-2 border-gray-200 dark:border-gray-700">
          <div x-sortable x-on:end="reorderItems($event.target.children)" class="space-y-2">
            @foreach($item->{$getChildrenKey()} as $child)
            <div x-sortable-item data-id="{{ $child->id }}" data-parent-id="{{ $item->id }}" class="flex items-center gap-2 p-2 bg-gray-50/80 dark:bg-gray-800/50 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
              @include('components.nested-set-list.item-content', [
              'item' => $child,
              'isParent' => false
              ])
            </div>

            {{-- Second Level Children (Sortable) --}}
            @if($child->{$getChildrenKey()}->count())
            <div class="pl-12 border-l-2 border-gray-200 dark:border-gray-700">
              <div x-sortable x-on:end="reorderItems($event.target.children)" class="space-y-2">
                @foreach($child->{$getChildrenKey()} as $grandchild)
                <div x-sortable-item data-id="{{ $grandchild->id }}" data-parent-id="{{ $child->id }}" class="flex items-center gap-2 p-2 bg-gray-50/60 dark:bg-gray-800/30 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                  @include('components.nested-set-list.item-content', [
                  'item' => $grandchild,
                  'isParent' => false
                  ])
                </div>
                @endforeach
              </div>
            </div>
            @endif
            @endforeach
          </div>
        </div>
        @endif
      </div>
      @endforeach
    </div>
  </div>
</x-dynamic-component>
