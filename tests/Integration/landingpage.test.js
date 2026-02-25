const {test, expect} = require('@playwright/test')
const { login } = require('./utils')

test('Landing page has blurb', async ({page, baseURL}) => {
  test.slow()
  // Simple test of page which is rendered with a Laravel blade.
  await page.goto(baseURL)
  const legend = page.locator('h2').first()
  await expect(legend).toHaveText('Our Global Impact')
})

test('Landing page renders static fixometer section', async ({page, baseURL}) => {
  test.slow()
  await page.goto(baseURL)

  await expect(page.locator('#fixometer-hero')).toHaveCount(1)
  await expect(page.locator('#fixometer-spacer')).toHaveCount(0)
  await expect(page.locator('#fixometer-sentinel')).toHaveCount(0)
})

test('Can log in', async({page, baseURL}) => {
  test.slow()
  await login(page, baseURL)
})
