/**
 * Filament Business Hours - weekly grid Alpine component.
 *
 * Renders the 7-day / 24-hour grid and handles every client-side gesture:
 * drag on an empty area to create a range, drag a range to move it, drag its
 * edges to resize it, click (or right-click, or Enter) to open the precise
 * editor. The canonical state is the Livewire-entangled `hours` object of
 * "HH:MM-HH:MM" strings per weekday, exactly the format stored in the
 * database and consumed by spatie/opening-hours, so every gesture ends by
 * writing normalized strings back into it.
 *
 * Visually, each day is a stack of hour cells and a logical range is drawn as
 * one segment per hour cell it covers: full cells are filled entirely,
 * partial cells are filled proportionally and carry a small badge with the
 * minutes ("30" on a 05:00 cell means the range starts or ends at 05:30).
 *
 * Overnight ranges ("22:00-02:00", only meaningful when the record has the
 * spatie "overflow" flag) cannot be produced by grid gestures; they are
 * rendered clamped to midnight and only editable through the precise editor.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('businessHoursGrid', (config) => ({
        state: config.state,
        days: config.days ?? [],
        labels: config.labels ?? {},
        step: Math.max(5, Math.min(60, config.step ?? 15)),
        hourHeight: config.hourHeight ?? 40,
        cellGap: config.cellGap ?? 4,
        allowOverflow: Boolean(config.allowOverflow),
        texts: Object.assign({
            closed: 'Closed',
            overnight: '+1',
            edit: 'Edit',
            invalid: 'The end must come after the start.',
        }, config.texts ?? {}),

        // The active gesture, or null. {type, day, col, pointerId, startY, moved, ...}
        drag: null,

        // The logical range the pointer is over, or null. {day, index}
        hovered: null,

        // The precise editor, or null. {day, index, raw, from, until, top, left}
        editor: null,
        editorInvalid: false,

        init() {
            // Close the editor when its range is changed or removed by the
            // server (save re-sync, copy day, clear day, ...).
            this.$watch('state', () => {
                if (this.editor && this.rangeAt(this.editor.day, this.editor.index) !== this.editor.raw) {
                    this.closeEditor();
                }
            });
        },

        /* ------------------------------------------------------------------ */
        /* Time helpers                                                       */
        /* ------------------------------------------------------------------ */

        toMinutes(value) {
            const [hours, minutes] = String(value).split(':').map(Number);

            return (hours * 60) + (minutes || 0);
        },

        toTime(minutes) {
            minutes = Math.max(0, Math.min(1440, Math.round(minutes)));

            return String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0');
        },

        // The value shown in an <input type="time">, which cannot express "24:00".
        toInputTime(minutes) {
            return minutes >= 1440 ? '00:00' : this.toTime(minutes);
        },

        snap(minutes) {
            return Math.round(minutes / this.step) * this.step;
        },

        snapFloor(minutes) {
            return Math.floor(minutes / this.step) * this.step;
        },

        /* ------------------------------------------------------------------ */
        /* State accessors                                                    */
        /* ------------------------------------------------------------------ */

        rangeAt(day, index) {
            return (this.state[day] ?? [])[index] ?? null;
        },

        // The stored ranges of a day, parsed into minute pairs for layout.
        parsed(day) {
            return (this.state[day] ?? []).map((raw, index) => {
                const [from, until] = String(raw).split('-');
                const start = this.toMinutes(from);
                const realEnd = until === '24:00' ? 1440 : this.toMinutes(until);
                const overnight = realEnd <= start;

                return {
                    index,
                    raw,
                    start,
                    end: overnight ? 1440 : realEnd,
                    realEnd,
                    overnight,
                };
            });
        },

        // The logical ranges a column displays: stored ranges with the active
        // gesture (creation preview, move, resize) overlaid.
        tiles(day) {
            let list = this.parsed(day);

            if (this.drag && this.drag.day === day) {
                if (this.drag.type === 'create') {
                    list = list.concat([{
                        index: -1,
                        raw: null,
                        start: this.drag.start,
                        end: this.drag.end,
                        realEnd: this.drag.end,
                        overnight: false,
                        preview: true,
                    }]);
                } else if (this.drag.type === 'move' || this.drag.type === 'resize') {
                    list = list.map((tile) => tile.index === this.drag.index
                        ? Object.assign({}, tile, { start: this.drag.start, end: this.drag.end, realEnd: this.drag.end, overnight: false, dragging: true })
                        : tile);
                }
            }

            return list;
        },

        // The visual layer: one segment per hour cell a range covers.
        segments(day) {
            const segments = [];

            for (const tile of this.tiles(day)) {
                const label = this.rangeLabel(tile);
                const firstHour = Math.floor(tile.start / 60);
                const lastHour = Math.ceil(tile.end / 60) - 1;

                for (let hour = firstHour; hour <= lastHour; hour++) {
                    const from = Math.max(tile.start, hour * 60);
                    const to = Math.min(tile.end, (hour + 1) * 60);

                    if (to <= from) {
                        continue;
                    }

                    const first = from === tile.start;
                    const last = to === tile.end;
                    const badges = [];

                    if (first && from % 60 !== 0) {
                        badges.push(String(from % 60).padStart(2, '0'));
                    }

                    if (last && to % 60 !== 0) {
                        badges.push(String(to % 60).padStart(2, '0'));
                    }

                    segments.push({
                        key: `${tile.index}-${hour}-${from}-${to}` + (tile.preview ? '-p' : ''),
                        index: tile.index,
                        hour,
                        from,
                        to,
                        first,
                        last,
                        badge: badges.join('-') || null,
                        rangeLabel: label,
                        preview: Boolean(tile.preview),
                        dragging: Boolean(tile.dragging),
                        overnight: tile.overnight,
                    });
                }
            }

            return segments;
        },

        // Serialize minute pairs back into normalized, sorted, merged strings.
        stringify(ranges) {
            const sameDay = [];
            const overnight = [];

            for (const range of ranges) {
                if (range.overnight) {
                    overnight.push(range.raw);

                    continue;
                }

                const start = Math.max(0, Math.min(1440, range.start));
                const end = Math.max(0, Math.min(1440, range.end));

                if (end - start >= 1) {
                    sameDay.push([start, end]);
                }
            }

            sameDay.sort((a, b) => (a[0] - b[0]) || (a[1] - b[1]));

            const merged = [];

            for (const [start, end] of sameDay) {
                const last = merged[merged.length - 1];

                if (last && start <= last[1]) {
                    last[1] = Math.max(last[1], end);
                } else {
                    merged.push([start, end]);
                }
            }

            const strings = merged.map(([start, end]) => this.toTime(start) + '-' + (end >= 1440 ? '24:00' : this.toTime(end)));

            return [...new Set(strings.concat(overnight))];
        },

        setDay(day, strings) {
            // Reassign the whole object so the Livewire entanglement always
            // notices the change, however deep it happened.
            this.state = Object.assign({}, this.state, { [day]: strings });
        },

        commitRanges(day, ranges) {
            this.setDay(day, this.stringify(ranges));
        },

        removeRange(day, index) {
            const ranges = this.parsed(day);

            if (index < 0 || index >= ranges.length) {
                return;
            }

            ranges.splice(index, 1);
            this.commitRanges(day, ranges);
            this.closeEditor();
        },

        // Keyboard-accessible alternative to drag-creation, used by the "+"
        // button in each day header: creates a one-hour range on the first
        // free hour of the day and opens the precise editor on it.
        addRange(day) {
            const sameDay = this.parsed(day).filter((range) => ! range.overnight);

            const candidates = [];

            for (let hour = 9; hour <= 23; hour++) {
                candidates.push(hour);
            }

            for (let hour = 8; hour >= 0; hour--) {
                candidates.push(hour);
            }

            // A candidate must neither overlap nor touch an existing range,
            // so the new range survives the merge pass untouched.
            const free = candidates.find((hour) => ! sameDay.some(
                (range) => (hour * 60) <= range.end && ((hour + 1) * 60) >= range.start,
            ));

            if (free === undefined) {
                return;
            }

            const start = free * 60;
            const end = start + 60;
            const ranges = this.parsed(day);

            ranges.push({ overnight: false, start, end });
            this.commitRanges(day, ranges);

            this.$nextTick(() => {
                const index = (this.state[day] ?? []).indexOf(
                    this.toTime(start) + '-' + (end >= 1440 ? '24:00' : this.toTime(end)),
                );

                if (index !== -1) {
                    this.openEditor(day, index);
                }
            });
        },

        /* ------------------------------------------------------------------ */
        /* Layout                                                             */
        /* ------------------------------------------------------------------ */

        segmentStyle(segment) {
            const cellTop = (segment.hour * this.hourHeight) + (this.cellGap / 2);
            const innerHeight = this.hourHeight - this.cellGap;
            const top = cellTop + (((segment.from - (segment.hour * 60)) / 60) * innerHeight);
            const height = ((segment.to - segment.from) / 60) * innerHeight;

            return `top:${top}px;height:${Math.max(height, 3)}px;`;
        },

        isHovered(day, segment) {
            return this.hovered !== null
                && this.hovered.day === day
                && this.hovered.index === segment.index
                && ! segment.preview;
        },

        rangeLabel(tile) {
            if (tile.overnight) {
                return this.toTime(tile.start) + ' - ' + this.toTime(tile.realEnd) + ' ' + this.texts.overnight;
            }

            return this.toTime(tile.start) + ' - ' + (tile.end >= 1440 ? '24:00' : this.toTime(tile.end));
        },

        segmentAria(day, segment) {
            return (this.labels[day] ?? day) + ', ' + segment.rangeLabel + '. ' + this.texts.edit;
        },

        dayTotal(day) {
            const minutes = this.parsed(day).reduce((total, tile) => total + (tile.overnight
                ? (1440 - tile.start) + tile.realEnd
                : tile.end - tile.start), 0);

            if (minutes === 0) {
                return this.texts.closed;
            }

            const hours = String(Math.floor(minutes / 60));
            const rest = minutes % 60;

            if (rest === 0) {
                return (this.texts.totalHours ?? ':hours h').replace(':hours', hours);
            }

            return (this.texts.totalHoursMinutes ?? ':hours h :minutes')
                .replace(':hours', hours)
                .replace(':minutes', String(rest).padStart(2, '0'));
        },

        minutesFromPointer(event, column) {
            const rect = column.getBoundingClientRect();
            const ratio = (event.clientY - rect.top) / rect.height;

            return Math.max(0, Math.min(1440, ratio * 1440));
        },

        /* ------------------------------------------------------------------ */
        /* Gestures                                                           */
        /* ------------------------------------------------------------------ */

        startCreate(event, day) {
            if (event.button !== 0 || this.drag) {
                return;
            }

            // A press on a range is handled by startRangePointer; a press on
            // the background while the editor is open just closes it.
            if (event.target.closest('[data-fbh-seg]') || event.target.closest('[data-fbh-editor]')) {
                return;
            }

            if (this.editor) {
                this.closeEditor();

                return;
            }

            const column = event.currentTarget;
            const anchor = Math.min(1440 - this.step, this.snapFloor(this.minutesFromPointer(event, column)));

            this.drag = {
                type: 'create',
                day,
                col: column,
                pointerId: event.pointerId,
                startY: event.clientY,
                moved: false,
                anchor,
                start: anchor,
                end: Math.min(1440, anchor + this.step),
            };

            column.setPointerCapture?.(event.pointerId);
        },

        startRangePointer(event, day, index) {
            if (event.button !== 0 || this.drag) {
                return;
            }

            const column = event.currentTarget.closest('[data-fbh-col]');
            const tile = this.parsed(day)[index];

            if (! column || ! tile) {
                return;
            }

            if (tile.overnight) {
                // Overnight ranges are only editable through the editor.
                this.drag = { type: 'tap', day, index, col: column, pointerId: event.pointerId, startY: event.clientY, moved: false };
            } else {
                this.drag = {
                    type: 'move',
                    day,
                    index,
                    col: column,
                    pointerId: event.pointerId,
                    startY: event.clientY,
                    moved: false,
                    grabOffset: this.minutesFromPointer(event, column) - tile.start,
                    duration: tile.end - tile.start,
                    start: tile.start,
                    end: tile.end,
                };
            }

            column.setPointerCapture?.(event.pointerId);
        },

        startResize(event, day, index, edge) {
            if (event.button !== 0 || this.drag) {
                return;
            }

            const column = event.target.closest('[data-fbh-col]');
            const tile = this.parsed(day)[index];

            if (! column || ! tile || tile.overnight) {
                return;
            }

            this.drag = {
                type: 'resize',
                day,
                index,
                edge,
                col: column,
                pointerId: event.pointerId,
                startY: event.clientY,
                moved: false,
                start: tile.start,
                end: tile.end,
            };

            column.setPointerCapture?.(event.pointerId);
        },

        onPointerMove(event) {
            const drag = this.drag;

            if (! drag || event.pointerId !== drag.pointerId) {
                return;
            }

            if (! drag.moved && Math.abs(event.clientY - drag.startY) <= 4) {
                return;
            }

            drag.moved = true;

            if (drag.type === 'tap') {
                return;
            }

            event.preventDefault();

            const raw = this.minutesFromPointer(event, drag.col);

            if (drag.type === 'create') {
                const cursor = this.snapFloor(raw);

                drag.start = Math.max(0, Math.min(drag.anchor, cursor));
                drag.end = Math.min(1440, Math.max(drag.anchor, cursor) + this.step);
            }

            if (drag.type === 'move') {
                const start = Math.max(0, Math.min(1440 - drag.duration, this.snap(raw - drag.grabOffset)));

                drag.start = start;
                drag.end = start + drag.duration;
            }

            if (drag.type === 'resize') {
                if (drag.edge === 'start') {
                    drag.start = Math.min(drag.end - this.step, Math.max(0, this.snap(raw)));
                } else {
                    drag.end = Math.max(drag.start + this.step, Math.min(1440, this.snap(raw)));
                }
            }
        },

        onPointerUp(event) {
            const drag = this.drag;

            if (! drag || event.pointerId !== drag.pointerId) {
                return;
            }

            this.drag = null;

            if (drag.type === 'tap') {
                if (! drag.moved) {
                    this.openEditor(drag.day, drag.index);
                }

                return;
            }

            if (drag.type === 'create') {
                const ranges = this.parsed(drag.day);

                if (drag.moved) {
                    ranges.push({ overnight: false, start: drag.start, end: drag.end });
                } else {
                    // A plain click creates a one-hour block.
                    ranges.push({ overnight: false, start: drag.anchor, end: Math.min(1440, drag.anchor + 60) });
                }

                this.commitRanges(drag.day, ranges);

                return;
            }

            // Move / resize.
            if (! drag.moved && drag.type === 'move') {
                this.openEditor(drag.day, drag.index);

                return;
            }

            const ranges = this.parsed(drag.day);

            if (ranges[drag.index]) {
                ranges[drag.index] = { overnight: false, start: drag.start, end: drag.end };
                this.commitRanges(drag.day, ranges);
            }
        },

        onPointerCancel() {
            this.drag = null;
        },

        /* ------------------------------------------------------------------ */
        /* Precise editor                                                     */
        /* ------------------------------------------------------------------ */

        openEditor(day, index) {
            const tile = this.parsed(day)[index];
            const column = this.$root.querySelector(`[data-fbh-col="${day}"]`);

            if (! tile || ! column) {
                return;
            }

            const editorWidth = 248;
            const bounds = column.offsetParent?.scrollWidth ?? column.parentElement?.scrollWidth ?? Infinity;
            let left = column.offsetLeft + column.offsetWidth + 8;

            if (left + editorWidth > bounds) {
                left = Math.max(8, column.offsetLeft - editorWidth - 8);
            }

            const top = Math.max(
                8,
                Math.min(
                    column.offsetTop + ((tile.start / 1440) * column.offsetHeight),
                    column.offsetTop + column.offsetHeight - 190,
                ),
            );

            this.editorInvalid = false;
            this.editor = {
                day,
                index,
                raw: tile.raw,
                from: this.toTime(tile.start),
                until: this.toInputTime(tile.realEnd),
                top,
                left,
            };

            this.$nextTick(() => this.$root.querySelector('[data-fbh-editor] input')?.focus());
        },

        closeEditor() {
            this.editor = null;
            this.editorInvalid = false;
        },

        applyEditor() {
            const editor = this.editor;

            if (! editor) {
                return;
            }

            const ranges = this.parsed(editor.day);

            if (! ranges[editor.index]) {
                this.closeEditor();

                return;
            }

            const start = this.toMinutes(editor.from);
            const until = editor.until === '00:00' ? 1440 : this.toMinutes(editor.until);

            if (until <= start) {
                if (! this.allowOverflow) {
                    this.editorInvalid = true;

                    return;
                }

                const raw = this.toTime(start) + '-' + editor.until;

                ranges[editor.index] = { overnight: true, raw };
                this.editorInvalid = false;
                this.setDay(editor.day, this.stringify(ranges));
                this.reanchorEditor(raw);

                return;
            }

            this.editorInvalid = false;
            ranges[editor.index] = { overnight: false, start, end: until };
            this.setDay(editor.day, this.stringify(ranges));
            this.reanchorEditor(this.toTime(start) + '-' + (until >= 1440 ? '24:00' : this.toTime(until)));
        },

        // After an edit the day is re-sorted and merged, so the edited range
        // may have moved (or been absorbed); point the editor back at it.
        reanchorEditor(raw) {
            if (! this.editor) {
                return;
            }

            const index = (this.state[this.editor.day] ?? []).indexOf(raw);

            if (index === -1) {
                this.closeEditor();

                return;
            }

            this.editor.index = index;
            this.editor.raw = raw;
        },

        applyEditorAndClose() {
            this.applyEditor();

            if (! this.editorInvalid) {
                this.closeEditor();
            }
        },

        deleteFromEditor() {
            if (this.editor) {
                this.removeRange(this.editor.day, this.editor.index);
            }
        },

        /* ------------------------------------------------------------------ */
        /* Keyboard                                                           */
        /* ------------------------------------------------------------------ */

        onSegmentKeydown(event, day, index) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.openEditor(day, index);
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                this.removeRange(day, index);
            }
        },
    }));
});
