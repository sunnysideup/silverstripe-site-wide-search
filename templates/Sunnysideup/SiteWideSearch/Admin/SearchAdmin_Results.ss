<% if $SearchResults %>
<style>
    .search-results-for-site-wide-search li {
        list-style: none;
    }
    .search-results-for-site-wide-search .disabled {
        color: #575757!important;
    }
    .search-results-for-site-wide-search a {
        font-weight: strong;
        display: inline-block;
    }
    .search-results-for-site-wide-search a.edit {
        font-size: 1.7rem;
    }

</style>
<ul class="search-results-for-site-wide-search">
    <% loop $SearchResults %>
    <li>
        <a <% if $HasCMSEditLink %>href="$CMSEditLink" class="edit"<% else %>class="edit disabled"<% end_if %>>
            ✎
        </a>

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
