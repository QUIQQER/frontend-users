<style>
    body {
        padding: 5mm 15mm 5mm 10mm;
    }

    h1 {
        font-size: 22px;
    }

    h2 {
        font-size: 18px;
    }

    .data-request-categories-entry > caption {
        background-color: #dedede;
        padding: 15px;
    }

    .data-request-categories-entry-header {
        background-color: #ededed;
    }

    .data-request-categories-entry-header > td {
        padding: 10px;
    }

    .data-request-categories-entry-header-spacer {
        height: 15px;
    }

    .data-request-categories-entry {
        page-break-inside: avoid;
    }
</style>

<h1>{locale group="quiqqer/gdpr" var="DataRequest.tpl.caption"}</h1>

<div class="data-request-info">
    {locale group="quiqqer/gdpr" var="DataRequest.tpl.info"
    userName=$User->getName()
    regulatoryAuthorityAddressLines=$regulatoryAuthorityAddressLines
    date=$date
    }
</div>

<div class="data-request-categories-list">
    <p>
        {locale group="quiqqer/gdpr" var="DataRequest.tpl.categories_info"}
    </p>
    <ul>
        {foreach $providers as $entry}
            <li>{$entry['title']}</li>
        {/foreach}
    </ul>
</div>

<div class="data-request-categories">
    {foreach $providers as $entry}
        <table class="data-request-categories-entry">
            <caption>
                {$entry['title']}
            </caption>
            <tbody>
            <!-- User data fields -->
            <tr class="data-request-categories-entry-header">
                <td colspan="2">
                    {locale group="quiqqer/gdpr" var="DataRequest.tpl.header.userData"}
                </td>
            </tr>
            {foreach $entry['userDataFields'] as $fieldTitle => $fieldValue}
                <tr>
                    <td>
                        {$fieldTitle}
                    </td>
                    <td>
                        {$fieldValue}
                    </td>
                </tr>
            {/foreach}

            <!-- Purpose -->
            <tr class="data-request-categories-entry-header-spacer"></tr>
            <tr class="data-request-categories-entry-header">
                <td colspan="2">
                    {locale group="quiqqer/gdpr" var="DataRequest.tpl.header.purpose"}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    {$entry['purpose']}
                </td>
            </tr>

            <!-- Recipients -->
            <tr class="data-request-categories-entry-header-spacer"></tr>
            <tr class="data-request-categories-entry-header">
                <td colspan="2">
                    {locale group="quiqqer/gdpr" var="DataRequest.tpl.header.recipients"}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    {$entry['recipients']}
                </td>
            </tr>

            <!-- Storage duration -->
            <tr class="data-request-categories-entry-header-spacer"></tr>
            <tr class="data-request-categories-entry-header">
                <td colspan="2">
                    {locale group="quiqqer/gdpr" var="DataRequest.tpl.header.storageDuration"}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    {$entry['storageDuration']}
                </td>
            </tr>

            <!-- Origin -->
            <tr class="data-request-categories-entry-header-spacer"></tr>
            <tr class="data-request-categories-entry-header">
                <td colspan="2">
                    {locale group="quiqqer/gdpr" var="DataRequest.tpl.header.origin"}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    {$entry['origin']}
                </td>
            </tr>

            {if !empty($entry['customText'])}
                <!-- Custom text -->
                <tr class="data-request-categories-entry-header-spacer"></tr>
                <tr class="data-request-categories-entry-header">
                    <td colspan="2">
                        {locale group="quiqqer/gdpr" var="DataRequest.tpl.header.customText"}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        {$entry['customText']}
                    </td>
                </tr>
            {/if}
            </tbody>
        </table>
    {/foreach}
</div>