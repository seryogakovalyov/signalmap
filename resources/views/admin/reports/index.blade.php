@extends('layouts.app')

@section('content')
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #475569;
            --primary: #0f766e;
            --danger: #b91c1c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Instrument Sans", "Segoe UI", sans-serif;
            background: linear-gradient(180deg, #ecfeff 0%, var(--bg) 22%, var(--bg) 100%);
            color: var(--text);
        }

        .shell {
            max-width: 1080px;
            margin: 0 auto;
            padding: 2rem 1rem 3rem;
        }

        .header {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        h1 {
            margin: 0 0 0.4rem;
            font-size: clamp(2rem, 5vw, 2.8rem);
        }

        p {
            margin: 0;
            color: var(--muted);
        }

        .notice {
            margin: 1rem 0 1.5rem;
            padding: 0.9rem 1rem;
            border-radius: 14px;
            background: rgba(15, 118, 110, 0.1);
            color: var(--primary);
        }

        .logout button {
            border: 0;
            border-radius: 999px;
            padding: 0.7rem 1rem;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            background: #0f172a;
            color: #fff;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.2rem;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.06);
        }

        .section {
            margin-top: 1.5rem;
        }

        .section-title {
            margin: 0 0 0.35rem;
            font-size: 1.4rem;
        }

        .section-copy {
            margin: 0 0 1rem;
            color: var(--muted);
        }

        .empty {
            padding: 2rem;
            text-align: center;
            color: var(--muted);
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .reports-table th,
        .reports-table td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .reports-table td {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .reports-table th {
            font-size: 0.82rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
            overflow-wrap: normal;
            word-break: normal;
            white-space: nowrap;
            hyphens: none;
        }

        .col-report {
            width: 52%;
        }

        .col-category {
            width: 12%;
        }

        .col-coordinates {
            width: 12%;
        }

        .col-submitted {
            width: 10%;
        }

        .col-actions {
            width: 14%;
        }

        .actions {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-form {
            margin: 0;
        }

        .report-title {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 1rem;
            line-height: 1.35;
        }

        .report-copy {
            display: block;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.45;
            color: var(--text);
        }

        .action-button {
            border: 0;
            border-radius: 999px;
            min-width: 112px;
            padding: 0.7rem 1rem;
            font: inherit;
            line-height: 1.1;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
        }

        .approve.action-button {
            background: var(--primary);
        }

        .reject.action-button {
            background: var(--danger);
        }

        .delete.action-button {
            background: #7f1d1d;
        }

        .pagination {
            margin-top: 1rem;
            overflow-x: auto;
        }

        .pagination nav {
            width: 100%;
        }

        .pagination nav > div:first-child {
            display: none;
        }

        .pagination nav > div:first-child a,
        .pagination nav > div:first-child span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.5rem;
            padding: 0.6rem 1rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-size: 0.92rem;
            line-height: 1;
            white-space: nowrap;
        }

        .pagination nav > div:last-child {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .pagination nav > div:last-child > div:first-child {
            flex: 1 1 auto;
        }

        .pagination p,
        .pagination p span {
            display: inline;
            min-width: 0;
            min-height: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            text-decoration: none;
            line-height: inherit;
        }

        .pagination nav > div:last-child > div:last-child > span {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .pagination nav > div:last-child > div:last-child a,
        .pagination nav > div:last-child > div:last-child [aria-disabled="true"] > span,
        .pagination nav > div:last-child > div:last-child [aria-current="page"] > span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            min-width: 2.5rem;
            min-height: 2.5rem;
            padding: 0;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-size: 0.92rem;
            line-height: 1;
            flex-shrink: 0;
        }

        .pagination nav > div:last-child > div:last-child [aria-current="page"] > span {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .pagination svg {
            width: 1rem;
            height: 1rem;
            display: block;
        }

        @media (max-width: 900px) {
            .reports-table,
            .reports-table thead,
            .reports-table tbody,
            .reports-table tr,
            .reports-table th,
            .reports-table td {
                display: block;
            }

            .reports-table thead {
                display: none;
            }

            .reports-table tr {
                border-bottom: 1px solid var(--border);
                padding: 0.8rem 0;
            }

            .reports-table td {
                border: 0;
                padding: 0.35rem 0;
            }

            .col-report,
            .col-category,
            .col-coordinates,
            .col-submitted,
            .col-actions {
                width: auto;
            }

            .pagination nav > div:first-child {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 0.5rem;
                width: 100%;
                flex-wrap: wrap;
            }

            .pagination nav > div:last-child {
                display: none;
            }
        }
    </style>

    <div class="shell">
        <div class="header">
            <div>
                <h1>Moderate pending reports</h1>
                <p>Review community submissions and either publish them to the public map or reject them.</p>
            </div>

            <form class="logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        <div class="card">
            @if ($pendingReports->isEmpty())
                <div class="empty">No pending reports to review.</div>
            @else
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th class="col-report">Report</th>
                            <th class="col-category">Category</th>
                            <th class="col-coordinates">Coordinates</th>
                            <th class="col-submitted">Submitted</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingReports as $report)
                            <tr>
                                <td class="col-report">
                                    <strong class="report-title">{{ $report->title }}</strong>
                                    <span class="report-copy">{{ $report->description }}</span>
                                </td>
                                <td class="col-category">
                                    <span style="color: {{ $report->category?->color ?? '#2563eb' }}">●</span>
                                    {{ $report->category?->name ?? 'Uncategorized' }}
                                </td>
                                <td class="col-coordinates">{{ $report->latitude }}, {{ $report->longitude }}</td>
                                <td class="col-submitted">{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="col-actions">
                                    <div class="actions">
                                        <form class="action-form" method="POST" action="{{ route('admin.reports.approve', $report) }}">
                                            @csrf
                                            <button class="action-button approve" type="submit">Approve</button>
                                        </form>

                                        <form class="action-form" method="POST" action="{{ route('admin.reports.reject', $report) }}">
                                            @csrf
                                            <button class="action-button reject" type="submit">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="pagination">
                    {{ $pendingReports->links() }}
                </div>
            @endif
        </div>

        <section class="section">
            <h2 class="section-title">Delete published points</h2>
            <p class="section-copy">Remove already published markers from the public map.</p>

            <div class="card">
                @if ($publishedReports->isEmpty())
                    <div class="empty">No published points available for deletion.</div>
                @else
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th class="col-report">Report</th>
                                <th class="col-category">Category</th>
                                <th class="col-coordinates">Coordinates</th>
                                <th class="col-submitted">Published</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($publishedReports as $report)
                                <tr>
                                    <td class="col-report">
                                        <strong class="report-title">{{ $report->title }}</strong>
                                        <span class="report-copy">{{ $report->description }}</span>
                                    </td>
                                    <td class="col-category">
                                        <span style="color: {{ $report->category?->color ?? '#2563eb' }}">●</span>
                                        {{ $report->category?->name ?? 'Uncategorized' }}
                                    </td>
                                    <td class="col-coordinates">{{ $report->latitude }}, {{ $report->longitude }}</td>
                                    <td class="col-submitted">{{ $report->updated_at?->format('Y-m-d H:i') }}</td>
                                    <td class="col-actions">
                                        <div class="actions">
                                            <form class="action-form" method="POST" action="{{ route('admin.reports.destroy', $report) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="action-button delete" type="submit">Delete point</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="pagination">
                        {{ $publishedReports->links() }}
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
