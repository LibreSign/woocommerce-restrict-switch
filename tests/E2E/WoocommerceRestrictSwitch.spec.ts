import { expect, Page, test } from '@playwright/test';

import { logInAsTheCustomer } from './support/store';

function plansInTheShop( page: Page ) {
	return page.locator( '.wp-block-post-title' );
}

test.describe( 'Restricting the plans a subscriber can switch to', () => {
	test( 'shows every plan in the shop to a visitor', async ( { page } ) => {
		await page.goto( '/shop/' );

		await expect( plansInTheShop( page ) ).toHaveText( [ 'Basic', 'Enterprise', 'Professional', 'Upgrade subscription' ] );
	} );

	test( 'hides from the shop the plans the subscriber cannot switch to', async ( { page } ) => {
		await logInAsTheCustomer( page );
		await page.goto( '/shop/' );

		await expect( plansInTheShop( page ) ).toHaveText( [ 'Basic', 'Enterprise', 'Upgrade subscription' ] );
	} );

	test( 'offers only the current plan and its upsells when the subscriber switches', async ( { page } ) => {
		await logInAsTheCustomer( page );
		await page.goto( '/my-account/subscriptions/' );
		await page.getByRole( 'link', { name: 'View' } ).first().click();
		await page.getByRole( 'link', { name: 'Switch', exact: true } ).click();

		await expect( page ).toHaveURL( /\/product\/upgrade-subscription\/\?switch-subscription=/ );
		await expect( page.locator( '.woocommerce-grouped-product-list-item__label' ) ).toHaveText( [ 'Basic', 'Enterprise' ] );
	} );
} );
