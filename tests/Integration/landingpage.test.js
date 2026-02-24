const {test, expect} = require('@playwright/test')
const { login } = require('./utils')

test('Landing page has blurb', async ({page, baseURL}) => {
  test.slow()
  // Simple test of page which is rendered with a Laravel blade.
  await page.goto(baseURL)
  const legend = page.locator('h2').first()
  await expect(legend).toHaveText('Our Global Impact')
})

test('Fixometer transitions between expanded and compact states without spacer drift', async ({page, baseURL}) => {
  test.slow()

  await page.goto(baseURL)

  const fixometer = page.locator('#fixometer-hero')

  await expect(fixometer).toHaveAttribute('data-fixometer-state', 'expanded')

  const initialSpacerHeight = await page.$eval('#fixometer-spacer', (el) => {
    return Math.round(parseFloat(window.getComputedStyle(el).height))
  })
  expect(initialSpacerHeight).toBe(0)

  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
  await expect(fixometer).toHaveAttribute('data-fixometer-state', 'compact')

  const compactSpacerHeight = await page.$eval('#fixometer-spacer', (el) => {
    return Math.round(parseFloat(window.getComputedStyle(el).height))
  })
  const expandedCssHeight = await page.$eval('#fixometer-hero', (el) => {
    return Math.round(parseFloat(window.getComputedStyle(el).getPropertyValue('--fixometer-expanded-height')))
  })

  expect(compactSpacerHeight).toBeGreaterThan(0)
  expect(Math.abs(compactSpacerHeight - expandedCssHeight)).toBeLessThanOrEqual(1)

  await page.evaluate(() => window.scrollTo(0, 0))
  await expect(fixometer).toHaveAttribute('data-fixometer-state', 'expanded')

  await page.waitForFunction(() => {
    const spacer = document.getElementById('fixometer-spacer')
    return Math.round(parseFloat(window.getComputedStyle(spacer).height)) === 0
  })
})

test('Fixometer respects reduced-motion preference', async ({page, baseURL}) => {
  test.slow()
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto(baseURL)

  const fixometer = page.locator('#fixometer-hero')

  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
  await expect(fixometer).toHaveAttribute('data-fixometer-state', 'compact')

  await page.evaluate(() => window.scrollTo(0, 0))
  await expect(fixometer).toHaveAttribute('data-fixometer-state', 'expanded')

  const isExpanding = await page.$eval('#fixometer-hero', (el) => {
    return el.classList.contains('fixometer--expanding')
  })
  expect(isExpanding).toBeFalsy()
})

test('Can log in', async({page, baseURL}) => {
  test.slow()
  await login(page, baseURL)
})
