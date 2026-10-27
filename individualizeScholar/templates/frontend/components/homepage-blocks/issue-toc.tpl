{**
 * Current issue on the home page
 *
 * Individualize Scholar: copy of Individualize's block that folds the table
 * of contents after $individualizeScholarFold articles ("Read more").
 *}
{if $issue}
  <section class="homepage homepage-issue-toc">
    <h2 class="sr-only">{translate key="journal.currentIssue"}</h2>
    {include
      file="frontend/components/issue-toc.tpl"
      issue=$issue
      headingStart="3"
      fold=$individualizeScholarFold
    }
  </section>
{/if}
