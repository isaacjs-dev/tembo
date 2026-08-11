import { describe, expect, it } from 'vitest';
import { appearanceEditor } from '../appearance-editor';

const definition = () => ({
    mode: 'canvas',
    height_mm: 36,
    canvas: { width_units: 1000, height_units: 360 },
    elements: [{
        id: 'title', type: 'text', token: 'assessment.title',
        x: 100, y: 20, width: 800, height: 60,
        align: 'center', font_size: 18, font_weight: 700, color: '#111827',
    }],
});

describe('appearanceEditor domain state', () => {
    it('adds, duplicates, bounds, aligns and removes domain elements', () => {
        const editor = appearanceEditor(definition(), 'assessment_header');
        editor.add('rectangle');
        const original = editor.element();
        expect(original.type).toBe('rectangle');

        editor.duplicate();
        expect(editor.definition.elements).toHaveLength(3);
        expect(editor.element().id).not.toBe(original.id);
        editor.updateElement('width', 5000);
        expect(editor.element().width).toBe(1000);
        expect(editor.element().x).toBe(0);
        editor.align('bottom');
        expect(editor.element().y + editor.element().height).toBe(360);
        editor.remove();
        expect(editor.definition.elements).toHaveLength(2);
    });

    it('supports undo and redo without storing Konva internals', () => {
        const editor = appearanceEditor(definition(), 'assessment_header');
        editor.add('line');
        expect(editor.definition.elements).toHaveLength(2);
        editor.undo();
        expect(editor.definition.elements).toHaveLength(1);
        editor.redo();
        expect(editor.definition.elements).toHaveLength(2);
        expect(editor.definitionString).not.toContain('Konva');
        expect(JSON.parse(editor.definitionString).canvas.width_units).toBe(1000);
    });
});
