{**
 * Article summary
 *
 * Used in issue tables of content, search results, and when browsing
 * by category. Accepts a number of params to modify behavior.
 *
 * @param Submission article
 * @param Publication publication (Optional) The version of the article
 *   to display. Default: $article->getCurrentPublication()
 * @param Context context (Optional) The context of this article.
 *   Default: $currentContext
 * @param string heading (Optional) The heading level to use for the
 *  title (eg - <h2>). Default: h2
 * @param bool cover (Optional) Whether or not to display the article
 *   cover image. Default: false
 * @param bool galleys (Optional) Whether or not to display the galley
 *   links. Default: false
 * @param bool abstract (Optional) Whether or not to show a snippet of
 *   the abstract. Default: false
 *
 * Individualize Scholar: copy of Individualize's template with the action
 * line under the authors (Show Abstract | Download PDF | DOI | Check in
 * Google Scholar). The PDF galley used for "Download PDF" gets no button of
 * its own; the other galley buttons stay.
 *}

{if !$heading}
  {assign var="heading" value="h2"}
{/if}
{if !$context}
  {assign var="context" value=$currentContext}
{/if}
{if !$publication}
  {assign var="publication" value=$article->getCurrentPublication()}
{/if}

<div class="
  article-summary
  {if $cover}
    article-summary-with-cover
  {/if}
">
  {if $cover && $publication->getLocalizedData('coverImage')}
    {assign var="coverImage" value=$publication->getLocalizedData('coverImage')}
    <img
      class="article-summary-cover"
      src="{$publication->getLocalizedCoverImageUrl($article->getData('contextId'))|escape}"
      alt="{$coverImage.altText|escape|default:''}"
    >
  {else}
    <div>{* Empty on purpose *}</div>
  {/if}
  <div class="article-summary-inner">
    <div class="article-summary-content">
      <a
        class="article-summary-link tab-focus"
        href="{url journal=$context->getPath() page="article" op="view" path=$article->getBestId()}"
      >
        <{$heading} class="article-summary-title">
          {$publication->getLocalizedFullTitle()|strip_unsafe_html}
        </{$heading}>
        <div class="article-summary-authors">
          {$article->getAuthorString()|escape}
        </div>
      </a>
      {if $abstract && $publication->getLocalizedData('abstract')}
        <div class="article-summary-abstract html-text" data-reveal data-height="12">
          {$publication->getLocalizedData('abstract')|strip_unsafe_html}
        </div>
      {/if}
      {individualize_scholar_actions
        assign="scholarActions"
        article=$article
        publication=$publication
        context=$context
        hideAbstract=$abstract
      }
      {$scholarActions.html}
    </div>

    {if $galleys && $article->getGalleys()|@count}
      {th_filter_galleys
        assign="primaryGalleys"
        galleys=$article->getGalleys()
        genreIds=$primaryGenreIds
        remotes=true
      }
      {assign var="scholarGalleyCount" value=$primaryGalleys|count}
      {if $scholarActions.pdfGalleyId}
        {foreach from=$primaryGalleys item="galley"}
          {if $galley->getId() == $scholarActions.pdfGalleyId}
            {assign var="scholarGalleyCount" value=$scholarGalleyCount-1}
          {/if}
        {/foreach}
      {/if}
      {if $scholarGalleyCount > 0}
        <div class="article-summary-galleys">
          {foreach from=$primaryGalleys item="galley"}
            {if $scholarActions.pdfGalleyId && $galley->getId() == $scholarActions.pdfGalleyId}
              {continue}
            {/if}
            {capture assign="galleyUrl"}{strip}
              {if $galley->getRemoteUrl()}
                {$galley->getRemoteUrl()}
              {else}
                {url page="article" op="view" path=$article->getBestId()|to_array:$galley->getBestGalleyId()}
              {/if}
            {/strip}{/capture}
            <a
              class="button article-summary-galley"
              href="{$galleyUrl}"
              aria-label="{translate
                key="submission.representationOfTitle"
                representation=$galley->getGalleyLabel()|escape
                title=$publication->getLocalizedFullTitle()|escape
              }"
            >
              {if $galley->isPdfGalley()}
                {include file="frontend/icons/download.svg"}
              {/if}
              <span class="button-truncate-text">
                {$galley->getGalleyLabel()|escape}
              </span>
            </a>
          {/foreach}
        </div>
      {/if}
    {/if}
  </div>
</div>