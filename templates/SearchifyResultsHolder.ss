<% if $Results %>
    <p>Showing <span class="search-total-results">{$Matches}</span> results for <span class="search-query-string">"{$QueryString}"</span></p>
    $Results
<% else %>
    <p>There are not results for <span class="search-query-string">{$QueryString}</span></p>
    <p>Try refining your search</p>
<% end_if %>