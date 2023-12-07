<% if $SearchResults %>
<ul class="search-results-for-site-wide-search">
    <% loop $SearchResults %>
    <li>
        <% if CMSThumbnail %>$CMSThumbnail<% end_if %>
        <% if $HasCMSEditLink %>
        <a href="$CMSEditLink" class="edit-from-quick-search" target="_parent">✎</a>
        <% else %>
        <a class="edit-from-quick-search disabled">&nbsp;</a>
        <% end_if %>
        —
        <a <% if $HasLink %> href="$Link"<% else %> class="disabled"<% end_if %> target="_blank">
            $Title ($SingularName)
        </a>
    </li>
    <% end_loop %>
</ul>
<% else %>
    <p class="message warning">
        No results where found!
    </p>
<% end_if %>
