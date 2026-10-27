{**
 * Author details (name, affiliation, ORCID) on the article page
 *
 * Individualize Scholar: copy of Individualize's template that adds the
 * country of the author (Country field of the author metadata) at the end
 * of the affiliation ("Universitas Indonesia, Indonesia"), or on its own
 * line when the author has no affiliation.
 *}
{assign var="count" value=$publication->getData('authors')|count}

<div class="
  author-details
  author-details-{$count}
  {if $count > 1}
    author-details-many
  {/if}
">
  {foreach from=$publication->getData('authors') item="author"}
    <div class="author-details-author">
      <div class="author-details-name">
        {$author->getFullName()|escape}
      </div>
      {individualize_scholar_country author=$author assign="scholarCountry"}
      {if $author->getLocalizedData('affiliation')}
        <div class="author-details-affiliation">
          {if $scholarCountry}
            {$author->getLocalizedData('affiliation')|regex_replace:"/[\s,]+$/":""|escape}<span class="ms-country">, {$scholarCountry|escape}</span>
          {else}
            {$author->getLocalizedData('affiliation')|escape}
          {/if}
          {if $author->getData('rorId')}
            <a href="{$author->getData('rorId')|escape}">{$rorIdIcon}</a>
          {/if}
        </div>
      {elseif $scholarCountry}
        <div class="author-details-affiliation ms-country-line">
          {$scholarCountry|escape}
        </div>
      {/if}
      {if $author->getData('orcid')}
        <a
          href="{$author->getData('orcid')|escape}"
          class="author-details-orcid tab-focus"
          target="_blank"
          aria-label="{translate
            key="common.editorialHistory.page.orcidLink"
            name=$author->getFullName()|escape
          }"
        >
          {if $author->getData('orcidAccessToken')}
            {include file="frontend/icons/orcid.svg"}
          {/if}
          {$author->getData('orcid')|escape}
        </a>
      {/if}
    </div>
  {/foreach}
</div>