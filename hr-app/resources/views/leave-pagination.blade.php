@if($items->hasPages())
<nav>@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">Previous</a>@endif
@if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">Next</a>@endif</nav>
@endif
