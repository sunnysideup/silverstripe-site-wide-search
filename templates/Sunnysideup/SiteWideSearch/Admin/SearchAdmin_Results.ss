<% if $SearchResults %>
<ul class="search-results-for-site-wide-search">
    <% if $IsQuickSearch %>
        <% loop $SearchResults %>
        <% if $HasCMSEditLink %>
        <li>
            <a href="$CMSEditLink" class="edit-from-quick-search">✎</a>
            <strong>$Object.i18n_singular_name:</strong> $Object.Title
            <% if CMSThumbnail %>$CMSThumbnail<% end_if %>
        </li>
        <% end_if %>
        <% end_loop %>
    <% else %>
        <% loop $SearchResults %>
        <li>
            <% if CMSThumbnail %>$CMSThumbnail<% end_if %>
            <% if $HasCMSEditLink %>
            <a href="$CMSEditLink" class="edit">✎</a>
            <% else %>
            <a href="$CMSEditLink" class="edit disabled">&nbsp;</a>
            <% end_if %>
            —
            <a <% if $HasLink %>href="$Link"<% else %>class="disabled"<% end_if %> target="new">
                $Object.Title ($Object.i18n_singular_name)
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
