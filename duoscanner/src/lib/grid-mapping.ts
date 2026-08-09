export interface GridTemplateCapacity {
  numCols: number;
  rowsPerCol: number;
}

export interface ResolvedGrid {
  rowsPerColumn: number;
  columns: number;
  totalQuestions: number;
}

export function isValidRowsPerPage(value: number | undefined): value is number {
  return Number.isInteger(value) && (value ?? 0) > 0 && (value ?? 0) <= 1000;
}

/** Resolve capacity without confusing QR contract/template versions. */
export function resolveGrid(
  questionCount: number,
  template: GridTemplateCapacity,
  signedRowsPerColumn?: number
): ResolvedGrid {
  const rowsPerColumn = isValidRowsPerPage(signedRowsPerColumn)
    ? signedRowsPerColumn
    : template.rowsPerCol;
  const columns = isValidRowsPerPage(signedRowsPerColumn)
    ? Math.max(1, Math.ceil(questionCount / rowsPerColumn))
    : template.numCols;

  return {
    rowsPerColumn,
    columns,
    totalQuestions: Math.min(questionCount, rowsPerColumn * columns),
  };
}

export function resolveGridPosition(index: number, rowsPerColumn: number): { col: number; row: number } {
  if (!Number.isInteger(index) || index < 0 || !isValidRowsPerPage(rowsPerColumn)) {
    throw new RangeError('Invalid OMR grid position.');
  }

  return {
    col: Math.floor(index / rowsPerColumn),
    row: index % rowsPerColumn,
  };
}
