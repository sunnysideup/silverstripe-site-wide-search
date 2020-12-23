<% if $SearchResults %>
<ul class="search-results-for-site-wide-search">
    <% loop $SearchResults %>
    <li>
        <% if $HasCMSEditLink %>
        <a href="$CMSEditLink" class="edit" style="font-family: monospace;">✎</a>
        <% else %>
        <a href="$CMSEditLink" class="edit disabled" style="font-family: monospace;">&nbsp;</a>
        <% end_if %>
        —
        <a <% if $HasLink %>href="$Link"<% else %>class="disabled"<% end_if %> target="new">
            $Object.Title ($Object.i18n_singular_name)
        </a>

    </li>
    <% end_loop %>
</ul>
<% else %>
    <p class="message warning">
        No results where found!
    </p>
<% end_if %>
