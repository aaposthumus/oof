@include('filament.rounds.styles')

<div>
    <p class="oof-heading">{{ $heading }}</p>

    @if ($rows === [])
        <p class="oof-muted">No {{ strtolower($heading) }} in the database yet.</p>
    @else
        <div class="oof-scroll">
            <table class="oof-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <details class="oof-details">
        <summary>Race control messages ({{ count($messages) }})</summary>

        @if ($messages === [])
            <p class="oof-muted">None in the database.</p>
        @else
            <div class="oof-scroll">
                <table class="oof-table">
                    <thead>
                        <tr><th>Time</th><th>Lap</th><th>Flag</th><th>Message</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($messages as $message)
                            <tr>
                                <td>{{ $message['time'] }}</td>
                                <td>{{ $message['lap'] }}</td>
                                <td>{{ $message['flag'] }}</td>
                                <td>{{ $message['text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </details>
</div>
