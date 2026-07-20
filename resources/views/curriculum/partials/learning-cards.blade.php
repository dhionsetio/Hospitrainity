<section class="hsp-learning-cards" aria-label="{{ $tableBlock['caption'] }}">
    <ul>
        @foreach($tableBlock['rows'] as $row)
            <li>
                <article>
                    <div class="hsp-learning-cards__lead">
                        <span>{{ $tableBlock['header'][0] }}</span>
                        <strong>{{ $row[0] ?? '' }}</strong>
                    </div>
                    @if(count($tableBlock['header']) > 1)
                        <dl>
                            @foreach(array_slice($tableBlock['header'], 1, null, true) as $index => $heading)
                                <div>
                                    <dt>{{ $heading }}</dt>
                                    <dd>{{ $row[$index] ?? '' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </article>
            </li>
        @endforeach
    </ul>
</section>
