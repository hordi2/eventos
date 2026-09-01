import { type ReactNode } from 'react';

interface Column<T> {
    key: string;
    header: string;
    render: (row: T) => ReactNode;
}

interface TableProps<T> {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    emptyMessage?: string;
}

export default function Table<T>({ columns, rows, rowKey, emptyMessage = 'Aucune donnée pour l\'instant.' }: TableProps<T>) {
    return (
        <div className="overflow-x-auto rounded-card bg-bg ring-1 ring-line">
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="border-b border-line font-label text-xs tracking-[0.1em] text-ink-soft uppercase">
                        {columns.map((column) => (
                            <th key={column.key} scope="col" className="px-6 py-4 font-medium">
                                {column.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={rowKey(row)} className="border-b border-line last:border-0">
                            {columns.map((column) => (
                                <td key={column.key} className="px-6 py-4 text-ink-soft">
                                    {column.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={columns.length} className="px-6 py-10 text-center text-ink-soft">
                                {emptyMessage}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
