const clone = (value) => JSON.parse(JSON.stringify(value));
const clamp = (value, min, max) => Math.max(min, Math.min(max, Number(value)));

export function appearanceEditor(initialDefinition, kind, initialAssets = {}) {
    return {
        kind,
        definition: clone(initialDefinition),
        selectedId: null,
        history: [],
        future: [],
        stage: null,
        transformer: null,
        definitionString: JSON.stringify(initialDefinition),
        assetPreviews: { ...initialAssets },
        imageCache: {},
        idSequence: 0,

        init() {
            this.sync();
            if (kind === 'assessment_header' && this.definition.mode === 'canvas') {
                this.$nextTick(() => this.mountCanvas());
            }
        },
        snapshot() {
            this.history.push(clone(this.definition));
            if (this.history.length > 30) this.history.shift();
            this.future = [];
        },
        sync(render = true) {
            this.definitionString = JSON.stringify(this.definition);
            if (render && this.stage) this.render();
        },
        undo() {
            if (!this.history.length) return;
            this.future.push(clone(this.definition));
            this.definition = this.history.pop();
            this.selectedId = null;
            this.sync();
        },
        redo() {
            if (!this.future.length) return;
            this.history.push(clone(this.definition));
            this.definition = this.future.pop();
            this.selectedId = null;
            this.sync();
        },
        element() {
            return this.definition.elements?.find((item) => item.id === this.selectedId) ?? null;
        },
        updateElement(field, value) {
            const element = this.element();
            if (!element) return;
            this.snapshot();
            element[field] = ['x', 'y', 'width', 'height', 'font_size', 'font_weight', 'border_width'].includes(field)
                ? Number(value) : value;
            this.keepInBounds(element);
            this.sync();
        },
        add(type) {
            this.snapshot();
            const id = this.uniqueId(type);
            const base = { id, type, x: 80, y: 80, width: 360, height: 60 };
            if (type === 'text' || type === 'field') Object.assign(base, {
                ...(type === 'field' ? { token: 'student.name' } : { text: 'Novo texto' }), align: 'left',
                font_size: 12, font_weight: type === 'text' ? 700 : 400, color: '#111827',
            });
            if (type === 'image') Object.assign(base, { asset_key: 'logo', width: 180, height: 120, alt_text: 'Logo' });
            if (type === 'line') Object.assign(base, { height: 8, width: 840, border_color: '#6b7280', border_width: 1 });
            if (type === 'rectangle') Object.assign(base, { width: 300, height: 120, border_color: '#6b7280', border_width: 1, fill: '#ffffff' });
            this.definition.elements.push(base);
            this.selectedId = id;
            this.sync();
        },
        remove() {
            if (!this.selectedId) return;
            this.snapshot();
            this.definition.elements = this.definition.elements.filter((item) => item.id !== this.selectedId);
            this.selectedId = null;
            this.sync();
        },
        duplicate() {
            const source = this.element();
            if (!source) return;
            this.snapshot();
            const copy = clone(source);
            copy.id = this.uniqueId(source.type);
            copy.x = clamp(copy.x + 24, 0, 1000 - copy.width);
            copy.y = clamp(copy.y + 24, 0, this.definition.canvas.height_units - copy.height);
            this.definition.elements.push(copy);
            this.selectedId = copy.id;
            this.sync();
        },
        align(axis) {
            const item = this.element();
            if (!item) return;
            this.snapshot();
            if (axis === 'left') item.x = 0;
            if (axis === 'center') item.x = (1000 - item.width) / 2;
            if (axis === 'right') item.x = 1000 - item.width;
            if (axis === 'top') item.y = 0;
            if (axis === 'middle') item.y = (this.definition.canvas.height_units - item.height) / 2;
            if (axis === 'bottom') item.y = this.definition.canvas.height_units - item.height;
            this.sync();
        },
        distribute(axis) {
            const items = this.definition.elements;
            if (items.length < 3) return;
            this.snapshot();
            const horizontal = axis === 'horizontal';
            const sorted = [...items].sort((a, b) => (horizontal ? a.x : a.y) - (horizontal ? b.x : b.y));
            const first = horizontal ? sorted[0].x : sorted[0].y;
            const last = horizontal
                ? sorted.at(-1).x + sorted.at(-1).width
                : sorted.at(-1).y + sorted.at(-1).height;
            const used = sorted.reduce((sum, item) => sum + (horizontal ? item.width : item.height), 0);
            const gap = Math.max(0, (last - first - used) / (sorted.length - 1));
            let cursor = first;
            sorted.forEach((item) => {
                if (horizontal) item.x = cursor;
                else item.y = cursor;
                cursor += (horizontal ? item.width : item.height) + gap;
                this.keepInBounds(item);
            });
            this.sync();
        },
        moveLayer(direction) {
            const index = this.definition.elements.findIndex((item) => item.id === this.selectedId);
            if (index < 0) return;
            const target = direction === 'front' ? this.definition.elements.length - 1 : 0;
            if (index === target) return;
            this.snapshot();
            const [item] = this.definition.elements.splice(index, 1);
            this.definition.elements.splice(target, 0, item);
            this.sync();
        },
        keepInBounds(item) {
            item.width = clamp(item.width, 10, 1000);
            item.height = clamp(item.height, 4, this.definition.canvas.height_units);
            item.x = clamp(item.x, 0, 1000 - item.width);
            item.y = clamp(item.y, 0, this.definition.canvas.height_units - item.height);
        },
        uniqueId(type) {
            let id;
            do {
                this.idSequence += 1;
                id = `${type}_${Date.now().toString(36)}_${this.idSequence.toString(36)}`;
            } while (this.definition.elements.some((item) => item.id === id));

            return id;
        },
        previewAsset(event, key = 'logo') {
            const file = event.target.files?.[0];
            if (!file) return;
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                this.assetPreviews[key] = reader.result;
                delete this.imageCache[key];
                this.render();
            });
            reader.readAsDataURL(file);
        },
        async mountCanvas() {
            const Konva = await window.loadKonva();
            const container = this.$refs.canvas;
            if (!container || this.stage) return;
            const width = Math.max(260, container.clientWidth);
            this.stage = new Konva.Stage({ container, width, height: width * this.definition.canvas.height_units / 1000 });
            this.stage.on('click tap', (event) => {
                this.selectedId = event.target === this.stage ? null : event.target.getAttr('elementId');
                this.render();
            });
            this.render();
            new ResizeObserver(() => {
                if (!this.stage) return;
                const nextWidth = Math.max(260, container.clientWidth);
                this.stage.size({ width: nextWidth, height: nextWidth * this.definition.canvas.height_units / 1000 });
                this.render();
            }).observe(container);
        },
        render() {
            if (!this.stage || !window.Konva) return;
            const Konva = window.Konva;
            this.stage.destroyChildren();
            const layer = new Konva.Layer();
            const sx = this.stage.width() / 1000;
            const sy = this.stage.height() / this.definition.canvas.height_units;
            layer.add(new Konva.Rect({ x: 0, y: 0, width: this.stage.width(), height: this.stage.height(), fill: '#ffffff', stroke: '#cbd5e1', strokeWidth: 1 }));
            this.definition.elements.forEach((item) => {
                const common = { x: item.x * sx, y: item.y * sy, width: item.width * sx, height: item.height * sy, draggable: true, elementId: item.id, name: 'appearance-node' };
                let node;
                if (item.type === 'text' || item.type === 'field') node = new Konva.Text({ ...common, text: item.text || `{{${item.token}}}`, fontSize: item.font_size * sx, fontStyle: item.font_weight >= 700 ? 'bold' : 'normal', fill: item.color, align: item.align, verticalAlign: 'middle' });
                else if (item.type === 'image' && this.imageCache[item.asset_key]) node = new Konva.Image({ ...common, image: this.imageCache[item.asset_key] });
                else if (item.type === 'image') {
                    node = new Konva.Rect({ ...common, fill: '#e0f2fe', stroke: '#0284c7', dash: [6, 4] });
                    const source = this.assetPreviews[item.asset_key];
                    if (source) {
                        const image = new Image();
                        image.onload = () => {
                            this.imageCache[item.asset_key] = image;
                            this.render();
                        };
                        image.src = source;
                    }
                }
                else if (item.type === 'line') node = new Konva.Line({ ...common, points: [0, 0, item.width * sx, 0], stroke: item.border_color, strokeWidth: item.border_width });
                else node = new Konva.Rect({ ...common, fill: item.fill, stroke: item.border_color, strokeWidth: item.border_width });
                node.on('dragstart transformstart', () => this.snapshot());
                node.on('dragend transformend', () => {
                    item.x = Math.round(node.x() / sx * 100) / 100;
                    item.y = Math.round(node.y() / sy * 100) / 100;
                    item.width = Math.round(node.width() * node.scaleX() / sx * 100) / 100;
                    item.height = Math.round(node.height() * node.scaleY() / sy * 100) / 100;
                    node.scale({ x: 1, y: 1 });
                    this.keepInBounds(item);
                    this.sync();
                });
                layer.add(node);
            });
            this.transformer = new Konva.Transformer({ rotateEnabled: false, flipEnabled: false, boundBoxFunc: (oldBox, newBox) => newBox.width < 10 || newBox.height < 4 ? oldBox : newBox });
            const selected = layer.findOne((node) => node.getAttr('elementId') === this.selectedId);
            if (selected) this.transformer.nodes([selected]);
            layer.add(this.transformer);
            this.stage.add(layer);
        },
    };
}
