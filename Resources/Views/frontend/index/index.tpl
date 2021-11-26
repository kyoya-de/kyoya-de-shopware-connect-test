{extends file='parent:frontend/index/index.tpl'}

{block name='frontend_index_header_javascript_tracking'}
    {$smarty.block.parent}
    {if {config name='makaira_tracking_page_id' namespace='MakairaConnect'} and {config name='makaira_tracking_active' namespace='MakairaConnect'}}
        <script type="text/javascript">
            var _paq = window._paq || [];

            window.addEventListener('load', function () {
                if ($.getCookiePreference('makairaTracking')) {
                  executeMakairaTracking();
                }
            });

            function executeMakairaTracking() {
                _paq.push(['enableLinkTracking']);

                {* PRODUCT DETAIL PAGE *}
                {if $Controller == "detail"}
                    _paq.push([
                        'setEcommerceView',
                        '{$sArticle.ordernumber}',
                        '{$sArticle.articleName|escape:'javascript'}',
                        '{$sCategoryInfo.name|escape:'javascript'}'
                    ]);
                    _paq.push(['trackPageView']);
                {/if}

                {* CATEGORY *}
                {if $Controller == "listing"}
                    _paq.push([
                        'setEcommerceView',
                        false,
                        false,
                        '{$sCategoryContent.name|escape:'javascript'}'
                    ]);
                    _paq.push(['trackPageView']);
                {/if}

                {* SEARCH *}
                {if $Controller == "search"}
                    _paq.push(['deleteCustomVariables', 'page']);
                    _paq.push(['trackSiteSearch', '{$sRequests.sSearchOrginal|escape:'javascript'}', false, '{$sSearchResults.sArticlesCount}']);
                    _paq.push(['trackPageView']);
                {/if}

                {* CHECKOUT/PURCHASE *}
                {if $sBasket.content and $sOrderNumber}
                    {assign var="discount" value=0}

                    {foreach $sBasket.content as $sBasketItem}
                        _paq.push(['addEcommerceItem',
                            '{$sBasketItem.ordernumber|escape:'javascript'}',
                            '{$sBasketItem.articlename|escape:'javascript'}',
                            '',
                            '{$sBasketItem.priceNumeric|round:2}',
                            '{$sBasketItem.quantity}',
                        ]);

                        {if $sBasketItem.priceNumeric < 0}
                            {$discount = $discount + $sBasketItem.priceNumeric}
                        {/if}
                    {/foreach}

                    {* Make discount positive, because discount items have a negative price *}
                    {$discount = $discount * -1}

                    {if $sAmountWthTax}
                        {assign var="revenue" value=$sAmountWithTax|replace:",":"."}
                    {else}
                        {assign var="revenue" value=$sAmount|replace:",":"."}
                    {/if}

                    {assign var="shipping" value=$sShippingcosts|replace:",":"."}
                    {assign var="subTotal" value=$revenue - $shipping}

                    {assign var="tax" value=0}
                    {foreach $sBasket.sTaxRates as $rate => $value}
                        {$tax = $tax + $value}
                    {/foreach}

                    _paq.push([
                        'trackEcommerceOrder',
                        '{$sOrderNumber}',
                        '{$revenue|round:2}',
                        '{$subTotal|round:2}',
                        '{$tax|round:2}',
                        '{$shipping|round:2}',
                        '{$discount}'
                    ]);
                {/if}

                {* ADD TO CART *}
                const ele = document.getElementsByName('sAddToBasket');
                if (ele[0]) {
                  ele[0].addEventListener("submit", function () {
                    const quantitySelect = document.getElementById('sQuantity');
                    _paq.push([
                      "addEcommerceItem",
                      '{$sArticle.ordernumber|escape:'javascript'}',
                      '{$sArticle.articleName|escape:'javascript'}',
                      '{$sArticle.categoryID}',
                      '{$sArticle.price_numeric|round:2}',
                      quantitySelect.value
                    ]);

                    const xmlHttp = new XMLHttpRequest();
                    xmlHttp.open("GET", '{url controller=checkout action=ajaxAmount}', false);
                    xmlHttp.send(null);
                    const response = JSON.parse(xmlHttp.response);
                    let cartTotal = response.amount;
                    cartTotal = cartTotal.match(/[+-]?\d+(\.\d+)?/g)[0];
                    _paq.push(["trackEcommerceCartUpdate", cartTotal]);
                  }, false);
                }

                {* A/B EXPERIMENTS *}
                function getCookie(cname) {
                    let name = cname + "=";
                    let decodedCookie = decodeURIComponent(document.cookie);
                    let ca = decodedCookie.split(';');
                    for (let i = 0; i < ca.length; i++) {
                        let c = ca[i];
                        while (c.charAt(0) == ' ') {
                            c = c.substring(1);
                        }
                        if (c.indexOf(name) == 0) {
                            return c.substring(name.length, c.length);
                        }
                    }
                    return "";
                }

                const makairaExperiments = getCookie('makairaExperiments');
                if (makairaExperiments !== "") {
                    const experiments = JSON.parse(makairaExperiments);
                    for (let i = 0; i < experiments.length; i++) {
                        _paq.push(['trackEvent', 'abtesting', experiments[i].experiment, experiments[i].variation]);
                    }
                }

                (function() {
                    var u="https://piwik.makaira.io/";
                    _paq.push(['setTrackerUrl', u+'piwik.php']);
                    _paq.push(['setSiteId', "{config name='makaira_tracking_page_id' namespace='MakairaConnect'}"]);
                    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
                    g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
                })();
            }
        </script>
    {/if}
{/block}
