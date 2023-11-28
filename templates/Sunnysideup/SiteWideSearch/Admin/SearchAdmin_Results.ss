<% if $SearchResults %>
<ul class="search-results-for-site-wide-search">
    <% if $IsQuickSearch %>
        <% loop $SearchResults %>
        <% if $HasCMSEditLink %>
        <li>
            <a href="$CMSEditLink" class="edit-from-quick-search">✎</a>
            <strong>$SingularName:</strong> $Title
            <% if CMSThumbnail %>$CMSThumbnail<% end_if %>
        </li>
        <% end_if %>
        <% end_loop %>
    <% else %>
        <% loop $SearchResults %>
        <li>
            <% if CMSThumbnail %>$CMSThumbnail<% end_if %>
            <% if $HasCMSEditLink %>
            <a href="$CMSEditLink" class="edit-from-quick-search" target="_edit">✎</a>
            <% else %>
            <a class="edit-from-quick-search disabled">&nbsp;</a>
            <% end_if %>
            —
            <a <% if $HasLink %>href="$Link"<% else %>class="disabled"<% end_if %> target="_new">
                $Title ($SingularName)
            </a>
        </li>
        <% end_loop %>
    <% end_if %>
</ul>
<% else %>
    <p class="message warning">
        No results where found!
    </p>
<% end_if %>
