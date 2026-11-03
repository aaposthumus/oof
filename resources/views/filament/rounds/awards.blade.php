@include('filament.rounds.styles')

<div>
    @if ($awards === [])
        <p class="oof-muted">No achievement awards for this round yet.</p>
    @else
        <div class="oof-scroll">
            <table class="oof-table">
                <thead>
                    <tr><th>Session</th><th>Award</th><th>Driver</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($awards as $award)
                        <tr>
                            <td>{{ $award['session'] }}</td>
                            <td><a href="{{ $award['url'] }}" class="underline">{{ $award['title'] }}</a>
                                @if ($award['description'])
                                    <div class="oof-muted" style="font-size: 0.75rem;">{{ $award['description'] }}</div>
                                @endif
                            </td>
                            <td>{{ $award['driver'] }}</td>
                            <td>{{ $award['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
