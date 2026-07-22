@php
    // Cell geometry, shared between the inline CSS variables and the Alpine
    // component so the two can never drift apart.
    $fbhHourHeight = 40;
    $fbhCellGap = 4;
@endphp

<x-filament-panels::page>
    <div class="fbh-page">

        {{-- ------------------------------------------------------------------ --}}
        {{-- Weekly grid                                                        --}}
        {{-- ------------------------------------------------------------------ --}}

        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-business-hours::filament-business-hours.grid.heading') }}
            </x-slot>

            <x-slot name="description">
                {{ __('filament-business-hours::filament-business-hours.grid.description', [
                    'timezone' => $effectiveTimezone,
                    'step' => $slotStep,
                ]) }}
            </x-slot>

            <div
                wire:key="fbh-grid-{{ $slotStep }}-{{ $this->overflow ? 'o1' : 'o0' }}"
                x-data="businessHoursGrid({
                    state: $wire.entangle('hours'),
                    days: @js($orderedDays),
                    labels: @js($dayLabels),
                    step: @js($slotStep),
                    hourHeight: @js($fbhHourHeight),
                    cellGap: @js($fbhCellGap),
                    allowOverflow: @js($this->overflow),
                    texts: @js([
                        'closed' => __('filament-business-hours::filament-business-hours.grid.closed'),
                        'overnight' => __('filament-business-hours::filament-business-hours.grid.overnight'),
                        'totalHours' => __('filament-business-hours::filament-business-hours.grid.total_hours'),
                        'totalHoursMinutes' => __('filament-business-hours::filament-business-hours.grid.total_hours_minutes'),
                        'edit' => __('filament-business-hours::filament-business-hours.grid.editor.edit'),
                        'invalid' => __('filament-business-hours::filament-business-hours.grid.editor.invalid'),
                    ]),
                })"
            >
                <div class="fbh-scroll">
                    <div class="fbh-grid" style="--fbh-hour: {{ $fbhHourHeight }}px; --fbh-gap: {{ $fbhCellGap }}px;">
                        <div class="fbh-corner"></div>

                        @foreach ($orderedDays as $day)
                            <div @class(['fbh-day-head', 'fbh-day-head--today' => $day === $today])>
                                <div class="fbh-day-title">
                                    <span class="fbh-day-name">{{ $dayLabels[$day] }}</span>
                                    <span class="fbh-day-total" x-text="dayTotal('{{ $day }}')"></span>
                                </div>

                                <div class="fbh-day-actions">
                                    <x-filament::icon-button
                                        icon="heroicon-o-plus"
                                        color="gray"
                                        size="sm"
                                        :label="__('filament-business-hours::filament-business-hours.grid.add_range')"
                                        :tooltip="__('filament-business-hours::filament-business-hours.grid.add_range')"
                                        x-on:click="addRange('{{ $day }}')"
                                    />

                                    <x-filament::icon-button
                                        icon="heroicon-o-document-duplicate"
                                        color="gray"
                                        size="sm"
                                        :label="__('filament-business-hours::filament-business-hours.grid.copy_day')"
                                        :tooltip="__('filament-business-hours::filament-business-hours.grid.copy_day')"
                                        wire:click="mountAction('copyDay', { day: '{{ $day }}' })"
                                        wire:loading.attr="disabled"
                                    />

                                    <x-filament::icon-button
                                        icon="heroicon-o-x-mark"
                                        color="gray"
                                        size="sm"
                                        :label="__('filament-business-hours::filament-business-hours.grid.clear_day')"
                                        :tooltip="__('filament-business-hours::filament-business-hours.grid.clear_day')"
                                        wire:click="clearDay('{{ $day }}')"
                                        wire:loading.attr="disabled"
                                    />
                                </div>
                            </div>
                        @endforeach

                        <div class="fbh-gutter">
                            @for ($hour = 0; $hour < 24; $hour++)
                                <div class="fbh-gutter-cell"><span>{{ sprintf('%02d:00', $hour) }}</span></div>
                            @endfor
                        </div>

                        @foreach ($orderedDays as $day)
                            <div
                                @class(['fbh-col', 'fbh-col--today' => $day === $today])
                                data-fbh-col="{{ $day }}"
                                x-on:pointerdown="startCreate($event, '{{ $day }}')"
                                x-on:pointermove="onPointerMove($event)"
                                x-on:pointerup="onPointerUp($event)"
                                x-on:pointercancel="onPointerCancel()"
                            >
                                @for ($hour = 0; $hour < 24; $hour++)
                                    <div class="fbh-cell"></div>
                                @endfor

                                <template x-for="segment in segments('{{ $day }}')" :key="`{{ $day }}-${segment.key}`">
                                    <div
                                        class="fbh-seg"
                                        data-fbh-seg
                                        :class="{
                                            'fbh-seg--preview': segment.preview,
                                            'fbh-seg--dragging': segment.dragging,
                                            'fbh-seg--overnight-end': segment.overnight && segment.last,
                                            'fbh-seg--hover': isHovered('{{ $day }}', segment),
                                        }"
                                        :style="segmentStyle(segment)"
                                        :tabindex="segment.first && ! segment.preview ? 0 : -1"
                                        :role="segment.first ? 'button' : null"
                                        :aria-label="segment.first ? segmentAria('{{ $day }}', segment) : null"
                                        :aria-hidden="segment.first ? null : 'true'"
                                        :title="segment.preview ? null : segment.rangeLabel"
                                        x-on:pointerenter="hovered = segment.preview ? null : { day: '{{ $day }}', index: segment.index }"
                                        x-on:pointerleave="hovered = null"
                                        x-on:pointerdown="startRangePointer($event, '{{ $day }}', segment.index)"
                                        x-on:contextmenu.prevent="openEditor('{{ $day }}', segment.index)"
                                        x-on:keydown="onSegmentKeydown($event, '{{ $day }}', segment.index)"
                                    >
                                        <span
                                            class="fbh-seg-handle fbh-seg-handle--start"
                                            x-show="segment.first && ! segment.preview && ! segment.overnight"
                                            x-on:pointerdown.stop="startResize($event, '{{ $day }}', segment.index, 'start')"
                                        ></span>

                                        <span
                                            class="fbh-seg-handle fbh-seg-handle--end"
                                            x-show="segment.last && ! segment.preview && ! segment.overnight"
                                            x-on:pointerdown.stop="startResize($event, '{{ $day }}', segment.index, 'end')"
                                        ></span>

                                        <span class="fbh-seg-badge" x-show="segment.badge" x-text="segment.badge"></span>

                                        <button
                                            type="button"
                                            class="fbh-seg-edit"
                                            x-show="segment.first && ! segment.preview"
                                            :tabindex="-1"
                                            :aria-label="texts.edit"
                                            x-on:pointerdown.stop=""
                                            x-on:click.stop="openEditor('{{ $day }}', segment.index)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" />
                                                <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        @endforeach

                        {{-- Precise range editor (opened by click / right-click / Enter on a range). --}}
                        <div
                            class="fbh-editor"
                            data-fbh-editor
                            x-cloak
                            x-show="editor !== null"
                            :class="{ 'fbh-editor--invalid': editorInvalid }"
                            :style="editor ? `top:${editor.top}px;left:${editor.left}px;` : ''"
                            x-on:click.outside="closeEditor()"
                            x-on:keydown.escape.prevent.stop="closeEditor()"
                            x-on:pointerdown.stop=""
                        >
                            <template x-if="editor">
                                <div style="display: contents">
                                    <div class="fbh-editor-title" x-text="labels[editor.day] ?? editor.day"></div>

                                    <div class="fbh-editor-times">
                                        <label class="fbh-editor-field">
                                            <span>{{ __('filament-business-hours::filament-business-hours.grid.editor.from') }}</span>
                                            <input
                                                type="time"
                                                step="60"
                                                x-model="editor.from"
                                                x-on:change="applyEditor()"
                                                x-on:keydown.enter.prevent="applyEditorAndClose()"
                                            />
                                        </label>

                                        <label class="fbh-editor-field">
                                            <span>{{ __('filament-business-hours::filament-business-hours.grid.editor.until') }}</span>
                                            <input
                                                type="time"
                                                step="60"
                                                x-model="editor.until"
                                                x-on:change="applyEditor()"
                                                x-on:keydown.enter.prevent="applyEditorAndClose()"
                                            />
                                        </label>
                                    </div>

                                    <p class="fbh-editor-error" x-show="editorInvalid">
                                        {{ __('filament-business-hours::filament-business-hours.grid.editor.invalid') }}
                                    </p>

                                    <div class="fbh-editor-actions">
                                        <button type="button" class="fbh-editor-btn fbh-editor-btn--delete" x-on:click="deleteFromEditor()">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482 41.03 41.03 0 0 0-2.365-.298V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                            </svg>
                                            {{ __('filament-business-hours::filament-business-hours.grid.editor.delete') }}
                                        </button>

                                        <button type="button" class="fbh-editor-btn fbh-editor-btn--done" x-on:click="applyEditorAndClose()">
                                            {{ __('filament-business-hours::filament-business-hours.grid.editor.done') }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <p class="fbh-legend">
                    {{ __('filament-business-hours::filament-business-hours.grid.hint') }}
                </p>
            </div>
        </x-filament::section>

        {{-- ------------------------------------------------------------------ --}}
        {{-- Exceptional dates                                                  --}}
        {{-- ------------------------------------------------------------------ --}}

        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-business-hours::filament-business-hours.exceptions.heading') }}
            </x-slot>

            <x-slot name="description">
                {{ __('filament-business-hours::filament-business-hours.exceptions.description') }}
            </x-slot>

            <ul role="list" class="fbh-entry-list">
                @forelse ($this->exceptions as $index => $exception)
                    <li class="fbh-entry" wire:key="fbh-exception-{{ $index }}-{{ $exception['key'] ?? '' }}">
                        <div class="fbh-entry-main">
                            <span class="fbh-entry-date">{{ $this->exceptionDateLabel($exception) }}</span>

                            @if ($exception['recurring'] ?? false)
                                <x-filament::badge color="info" size="sm">
                                    {{ __('filament-business-hours::filament-business-hours.exceptions.recurring_badge') }}
                                </x-filament::badge>
                            @endif

                            @if ($exception['closed'] ?? true)
                                <x-filament::badge color="danger" size="sm">
                                    {{ __('filament-business-hours::filament-business-hours.exceptions.closed_badge') }}
                                </x-filament::badge>
                            @else
                                <span class="fbh-entry-hours">{{ $this->exceptionHoursLabel($exception) }}</span>
                            @endif

                            @if ($exception['label'] ?? null)
                                <span class="fbh-entry-label">{{ $exception['label'] }}</span>
                            @endif
                        </div>

                        <div class="fbh-entry-actions">
                            <x-filament::icon-button
                                icon="heroicon-o-pencil-square"
                                color="gray"
                                size="sm"
                                :label="__('filament-business-hours::filament-business-hours.exceptions.edit')"
                                wire:click="mountAction('editException', { index: {{ $index }} })"
                                wire:loading.attr="disabled"
                            />

                            <x-filament::icon-button
                                icon="heroicon-o-trash"
                                color="danger"
                                size="sm"
                                :label="__('filament-business-hours::filament-business-hours.exceptions.remove')"
                                wire:click="mountAction('removeException', { index: {{ $index }} })"
                                wire:loading.attr="disabled"
                            />
                        </div>
                    </li>
                @empty
                    <li class="fbh-entry-empty">
                        {{ __('filament-business-hours::filament-business-hours.exceptions.empty') }}
                    </li>
                @endforelse
            </ul>

            <div class="fbh-add-row">
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-plus"
                    wire:click="mountAction('addException')"
                    wire:loading.attr="disabled"
                >
                    {{ __('filament-business-hours::filament-business-hours.exceptions.add') }}
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- ------------------------------------------------------------------ --}}
        {{-- Vacation periods                                                   --}}
        {{-- ------------------------------------------------------------------ --}}

        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-business-hours::filament-business-hours.vacations.heading') }}
            </x-slot>

            <x-slot name="description">
                {{ __('filament-business-hours::filament-business-hours.vacations.description') }}
            </x-slot>

            <ul role="list" class="fbh-entry-list">
                @forelse ($this->vacations as $index => $vacation)
                    @php($vacationDays = $this->vacationDaysCount($vacation))

                    <li class="fbh-entry" wire:key="fbh-vacation-{{ $index }}-{{ $vacation['from'] ?? '' }}">
                        <div class="fbh-entry-main">
                            <span class="fbh-entry-date">{{ $this->vacationRangeLabel($vacation) }}</span>

                            @if ($vacationDays !== null)
                                <x-filament::badge color="gray" size="sm">
                                    {{ trans_choice('filament-business-hours::filament-business-hours.vacations.days_count', $vacationDays, ['count' => $vacationDays]) }}
                                </x-filament::badge>
                            @endif

                            @if ($vacation['label'] ?? null)
                                <span class="fbh-entry-label">{{ $vacation['label'] }}</span>
                            @endif
                        </div>

                        <div class="fbh-entry-actions">
                            <x-filament::icon-button
                                icon="heroicon-o-pencil-square"
                                color="gray"
                                size="sm"
                                :label="__('filament-business-hours::filament-business-hours.vacations.edit')"
                                wire:click="mountAction('editVacation', { index: {{ $index }} })"
                                wire:loading.attr="disabled"
                            />

                            <x-filament::icon-button
                                icon="heroicon-o-trash"
                                color="danger"
                                size="sm"
                                :label="__('filament-business-hours::filament-business-hours.vacations.remove')"
                                wire:click="mountAction('removeVacation', { index: {{ $index }} })"
                                wire:loading.attr="disabled"
                            />
                        </div>
                    </li>
                @empty
                    <li class="fbh-entry-empty">
                        {{ __('filament-business-hours::filament-business-hours.vacations.empty') }}
                    </li>
                @endforelse
            </ul>

            <div class="fbh-add-row">
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-plus"
                    wire:click="mountAction('addVacation')"
                    wire:loading.attr="disabled"
                >
                    {{ __('filament-business-hours::filament-business-hours.vacations.add') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
