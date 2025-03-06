<section class="dashboard__block">

    <div class="dashboard__block__header dashboard__block__header--hottopics">
        <h4>@lang('partials.hot_topics')</h4>
    </div>

    <div class="dashboard__block__content dashboard__block__content--table">

        <p>@lang('partials.hot_topics_text')</p>
            <ol class="list-unstyled dashboard__list-topics">
                @php( $count = 1 )
                @foreach( $hot_topics['talk_hot_topics'] as $hot_topic )
                    <li @if( isset($hot_topics['talk_categories'][$hot_topic->category_id]) ) style="border-color: #{{{ $hot_topics['talk_categories'][$hot_topic->category_id]->color }}}; border-bottom:1px solid #eee" @endif>
                        <span class="hottopic" >
                            @if( strtotime($hot_topic->created_at) > strtotime('-4 days') )
                                <span class="badge badge-danger">NEW!</span>
                            @endif
                            @if( isset($hot_topics['talk_categories'][$hot_topic->category_id]) ) 
                            <div><span style="display:inline-block; width:9px; height:9px; margin-right:5px; background-color: #{{{ $hot_topics['talk_categories'][$hot_topic->category_id]->color }}};"></span><span style="font-size:.8706em; font-weight:bold" >{{{ $hot_topics['talk_categories'][$hot_topic->category_id]->name }}}</span></div>
                            @endif
                        </span>
                    </li>
                    @if ($count > 4) @break @endif
                    @php( $count++ )
                @endforeach
            </ol>
    </div>
</section>
