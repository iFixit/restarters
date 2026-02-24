const STATES = {
  EXPANDED: 'expanded',
  COMPACT: 'compact',
  EXPANDING: 'expanding'
}

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)'
const DEFAULT_TRANSITION_FALLBACK_MS = 500

function onMediaQueryChange(query, handler) {
  if (!query) {
    return function() {}
  }

  if (query.addEventListener) {
    query.addEventListener('change', handler)
    return function() {
      query.removeEventListener('change', handler)
    }
  }

  query.addListener(handler)
  return function() {
    query.removeListener(handler)
  }
}

export default function initFixometerTransition(options = {}) {
  const sentinel = document.getElementById('fixometer-sentinel')
  const fixometer = document.getElementById('fixometer-hero')
  const spacer = document.getElementById('fixometer-spacer')

  if (!sentinel || !fixometer || !spacer || !window.IntersectionObserver) {
    return
  }

  if (fixometer.dataset.fixometerTransitionReady === 'true') {
    return
  }

  fixometer.dataset.fixometerTransitionReady = 'true'

  const transitionFallbackMs = options.transitionFallbackMs || DEFAULT_TRANSITION_FALLBACK_MS
  const reducedMotionMedia = window.matchMedia ? window.matchMedia(REDUCED_MOTION_QUERY) : null

  let prefersReducedMotion = reducedMotionMedia ? reducedMotionMedia.matches : false
  let state = null
  let expandedHeight = 0
  let lastIntersecting = null
  let intersectionObserver = null
  let resizeObserver = null
  let expandFallbackTimer = null
  let pendingMeasureFrame = null

  function setSpacerHeight(height) {
    spacer.style.height = Math.max(0, Math.round(height)) + 'px'
  }

  function setState(nextState) {
    if (state === nextState) {
      return
    }

    state = nextState
    fixometer.dataset.fixometerState = nextState
    fixometer.classList.toggle('fixometer--compact', nextState === STATES.COMPACT)
    fixometer.classList.toggle('fixometer--expanding', nextState === STATES.EXPANDING)
  }

  function handleIntersection(entries) {
    const entry = entries[0]
    if (!entry) {
      return
    }

    const isIntersecting = entry.isIntersecting
    if (lastIntersecting === isIntersecting) {
      return
    }

    lastIntersecting = isIntersecting

    if (isIntersecting) {
      enterExpanded()
      return
    }

    enterCompact()
  }

  function initIntersectionObserver() {
    if (expandedHeight <= 0) {
      return
    }

    if (intersectionObserver) {
      intersectionObserver.disconnect()
    }

    intersectionObserver = new IntersectionObserver(handleIntersection, {
      threshold: 0,
      rootMargin: '-' + expandedHeight + 'px 0px 0px 0px'
    })

    intersectionObserver.observe(sentinel)
  }

  function setExpandedHeight(height) {
    const nextHeight = Math.max(1, Math.round(height))
    if (!nextHeight || nextHeight === expandedHeight) {
      return
    }

    expandedHeight = nextHeight
    fixometer.style.setProperty('--fixometer-expanded-height', expandedHeight + 'px')

    if (state !== STATES.EXPANDED) {
      setSpacerHeight(expandedHeight)
    }

    initIntersectionObserver()
  }

  function scheduleExpandedHeightMeasure() {
    if (pendingMeasureFrame) {
      window.cancelAnimationFrame(pendingMeasureFrame)
      pendingMeasureFrame = null
    }

    pendingMeasureFrame = window.requestAnimationFrame(function() {
      pendingMeasureFrame = null

      if (state !== STATES.EXPANDED) {
        return
      }

      setExpandedHeight(fixometer.offsetHeight)
    })
  }

  function handleExpandTransitionEnd(event) {
    if (event.target !== fixometer) {
      return
    }

    finishExpanding()
  }

  function clearExpandTransition() {
    fixometer.removeEventListener('transitionend', handleExpandTransitionEnd)

    if (expandFallbackTimer) {
      window.clearTimeout(expandFallbackTimer)
      expandFallbackTimer = null
    }
  }

  function finishExpanding() {
    if (state !== STATES.EXPANDING) {
      return
    }

    clearExpandTransition()
    setState(STATES.EXPANDED)
    setSpacerHeight(0)
    scheduleExpandedHeightMeasure()
  }

  function enterCompact() {
    if (state === STATES.COMPACT) {
      return
    }

    clearExpandTransition()
    setSpacerHeight(expandedHeight)
    setState(STATES.COMPACT)
  }

  function enterExpanded() {
    if (state === STATES.EXPANDED) {
      return
    }

    if (prefersReducedMotion) {
      clearExpandTransition()
      setState(STATES.EXPANDED)
      setSpacerHeight(0)
      scheduleExpandedHeightMeasure()
      return
    }

    if (state === STATES.EXPANDING) {
      return
    }

    setState(STATES.EXPANDING)
    setSpacerHeight(expandedHeight)
    fixometer.addEventListener('transitionend', handleExpandTransitionEnd)
    expandFallbackTimer = window.setTimeout(finishExpanding, transitionFallbackMs)
  }

  function handleResize() {
    if (state !== STATES.EXPANDED) {
      return
    }

    scheduleExpandedHeightMeasure()
  }

  function handleReducedMotionPreference(event) {
    prefersReducedMotion = event.matches

    if (prefersReducedMotion && state === STATES.EXPANDING) {
      finishExpanding()
    }
  }

  setState(STATES.EXPANDED)
  setSpacerHeight(0)
  setExpandedHeight(fixometer.offsetHeight)

  if (window.ResizeObserver) {
    resizeObserver = new ResizeObserver(function() {
      if (state === STATES.EXPANDED) {
        scheduleExpandedHeightMeasure()
      }
    })
    resizeObserver.observe(fixometer)
  }

  window.addEventListener('resize', handleResize, { passive: true })
  const removeReducedMotionListener = onMediaQueryChange(reducedMotionMedia, handleReducedMotionPreference)

  return function destroyFixometerTransition() {
    clearExpandTransition()

    if (pendingMeasureFrame) {
      window.cancelAnimationFrame(pendingMeasureFrame)
      pendingMeasureFrame = null
    }

    if (intersectionObserver) {
      intersectionObserver.disconnect()
    }

    if (resizeObserver) {
      resizeObserver.disconnect()
    }

    window.removeEventListener('resize', handleResize)
    removeReducedMotionListener()
    delete fixometer.dataset.fixometerTransitionReady
  }
}
