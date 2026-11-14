import { expect, Page } from '@playwright/test';

export async function logInAsTheCustomer( page: Page ): Promise< void > {
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( 'customer' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await expect( page ).not.toHaveURL( /wp-login\.php/ );
}
