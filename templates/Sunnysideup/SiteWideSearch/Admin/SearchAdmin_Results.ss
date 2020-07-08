<% if $SearchResults %>
<ul class="search-results-for-site-wide-search">
    <% loop $SearchResults %>
    <li>
        <a <% if $HasCMSEditLink %>href="$CMSEditLink" class="edit"<% else %>class="edit disabled"<% end_if %>>
            ✎
        </a>
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
