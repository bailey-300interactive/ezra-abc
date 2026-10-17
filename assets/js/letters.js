/*
 * Letter shapes for tracing, as SVG path strokes in the usual school stroke order.
 * Capitals: cap height y 0 → baseline y 100.
 * Lowercase: ascender y 0, x-height y 45, baseline y 100, descender down to ~140.
 * Each letter is a list of strokes; tiny strokes (the dot on i and j) are "tap" dots.
 */
window.ABC_STROKES = {
  upper: {
    A: ['M40 0 L6 100', 'M40 0 L74 100', 'M19 64 L61 64'],
    B: ['M10 0 L10 100', 'M10 0 L44 0 C70 0 70 48 44 48 L10 48', 'M10 48 L48 48 C78 48 78 100 48 100 L10 100'],
    C: ['M74 20 A40 50 0 1 0 74 80'],
    D: ['M10 0 L10 100', 'M10 0 L34 0 C86 0 86 100 34 100 L10 100'],
    E: ['M10 0 L10 100', 'M10 0 L64 0', 'M10 50 L54 50', 'M10 100 L64 100'],
    F: ['M10 0 L10 100', 'M10 0 L64 0', 'M10 50 L54 50'],
    G: ['M74 20 A40 50 0 1 0 80 62', 'M52 60 L80 60 L80 100'],
    H: ['M10 0 L10 100', 'M70 0 L70 100', 'M10 50 L70 50'],
    I: ['M40 0 L40 100', 'M14 0 L66 0', 'M14 100 L66 100'],
    J: ['M62 0 L62 70 C62 108 12 108 10 76'],
    K: ['M10 0 L10 100', 'M70 0 L10 60', 'M30 41 L72 100'],
    L: ['M10 0 L10 100 L64 100'],
    M: ['M8 100 L8 0 L45 66 L82 0 L82 100'],
    N: ['M10 100 L10 0 L70 100 L70 0'],
    O: ['M45 0 A37 50 0 0 0 45 100 A37 50 0 0 0 45 0'],
    P: ['M10 0 L10 100', 'M10 0 L44 0 C74 0 74 54 44 54 L10 54'],
    Q: ['M45 0 A37 50 0 0 0 45 100 A37 50 0 0 0 45 0', 'M54 70 L86 104'],
    R: ['M10 0 L10 100', 'M10 0 L44 0 C74 0 74 54 44 54 L10 54', 'M40 54 L72 100'],
    S: ['M70 16 C60 -6 10 -6 12 25 C14 50 70 44 70 74 C70 108 14 108 8 84'],
    T: ['M6 0 L74 0', 'M40 0 L40 100'],
    U: ['M10 0 L10 64 C10 108 70 108 70 64 L70 0'],
    V: ['M6 0 L40 100 L74 0'],
    W: ['M3 0 L22 100 L45 32 L68 100 L87 0'],
    X: ['M8 0 L72 100', 'M72 0 L8 100'],
    Y: ['M8 0 L40 50', 'M72 0 L40 50 L40 100'],
    Z: ['M8 0 L72 0 L8 100 L72 100']
  },
  lower: {
    a: ['M60 55 A26 27.5 0 1 0 60 90', 'M62 45 L62 100'],
    b: ['M10 0 L10 100', 'M10 72 A27 27.5 0 0 1 64 72 A27 27.5 0 0 1 10 72'],
    c: ['M60 55 A27 27.5 0 1 0 60 90'],
    d: ['M60 55 A26 27.5 0 1 0 60 90', 'M62 0 L62 100'],
    e: ['M12 72 L64 72 A27 27.5 0 1 0 58 92'],
    f: ['M58 8 C50 -4 28 -1 28 20 L28 100', 'M10 45 L50 45'],
    g: ['M60 55 A26 27.5 0 1 0 60 90', 'M62 45 L62 115 C62 145 14 145 10 122'],
    h: ['M10 0 L10 100', 'M10 62 C18 38 62 38 62 65 L62 100'],
    i: ['M30 45 L30 100', 'M30 20 L30 22'],
    j: ['M42 45 L42 118 C42 144 10 144 6 126', 'M42 20 L42 22'],
    k: ['M10 0 L10 100', 'M56 45 L10 78', 'M28 65 L58 100'],
    l: ['M25 0 L25 100'],
    m: ['M8 45 L8 100', 'M8 62 C14 38 42 38 42 62 L42 100', 'M42 62 C48 38 76 38 76 62 L76 100'],
    n: ['M10 45 L10 100', 'M10 62 C18 38 60 38 60 65 L60 100'],
    o: ['M37 45 A27 27.5 0 0 0 37 100 A27 27.5 0 0 0 37 45'],
    p: ['M10 45 L10 140', 'M10 72 A27 27.5 0 0 1 64 72 A27 27.5 0 0 1 10 72'],
    q: ['M60 55 A26 27.5 0 1 0 60 90', 'M62 45 L62 140'],
    r: ['M12 45 L12 100', 'M12 66 C18 44 40 40 56 50'],
    s: ['M56 53 C46 39 12 41 14 60 C16 76 57 68 57 86 C57 105 17 105 10 92'],
    t: ['M30 10 L30 100', 'M10 45 L52 45'],
    u: ['M10 45 L10 76 C10 106 58 106 60 76', 'M60 45 L60 100'],
    v: ['M5 45 L32 100 L59 45'],
    w: ['M3 45 L18 100 L36 58 L54 100 L69 45'],
    x: ['M8 45 L56 100', 'M56 45 L8 100'],
    y: ['M6 45 L34 98', 'M62 45 L30 116 C24 132 16 138 6 136'],
    z: ['M8 45 L56 45 L8 100 L56 100']
  },
  /* Digits use the capital-letter box (y 0 → 100). Bigger numbers are built from these, side by side. */
  digits: {
    0: ['M37 0 A30 50 0 0 0 37 100 A30 50 0 0 0 37 0'],
    1: ['M12 24 L38 0 L38 100'],
    2: ['M10 26 C12 -6 66 -8 66 28 C66 54 34 70 8 100 L68 100'],
    3: ['M10 16 C22 -6 66 -4 64 24 C62 44 44 48 30 48 C48 48 70 56 68 76 C66 108 18 106 8 86'],
    4: ['M48 0 L6 66 L72 66', 'M52 34 L52 100'],
    5: ['M18 0 L14 46 C34 30 70 36 68 68 C66 106 18 106 8 84', 'M18 0 L64 0'],
    6: ['M62 10 C48 -6 10 0 8 54 C6 90 22 100 38 100 C58 100 68 86 68 70 C68 52 54 42 38 42 C22 42 10 52 8 66'],
    7: ['M8 0 L68 0 L28 100'],
    8: ['M38 48 C14 48 12 0 38 0 C64 0 62 48 38 48 C8 48 8 100 38 100 C68 100 68 48 38 48'],
    9: ['M66 30 C66 10 52 0 37 0 C20 0 8 12 8 30 C8 48 22 58 37 58 C54 58 66 46 66 30 L66 100']
  }
};

/* How numbers are said out loud, 0 to 100. */
window.ABC_NUMBER_WORD = n => {
  const ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
    'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
  const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
  if (n >= 100) return 'one hundred';
  if (n < 20) return ones[n];
  return tens[Math.floor(n / 10)] + (n % 10 ? '-' + ones[n % 10] : '');
};

/* Something to count for each number (by its last digit): emoji, one, many. */
window.ABC_NUMBER_THINGS = [
  ['🍪', 'cookie', 'cookies'], ['🐶', 'puppy', 'puppies'], ['👟', 'shoe', 'shoes'], ['🚗', 'car', 'cars'],
  ['🍎', 'apple', 'apples'], ['⭐', 'star', 'stars'], ['🦆', 'duck', 'ducks'], ['🐟', 'fish', 'fish'],
  ['🍓', 'strawberry', 'strawberries'], ['🐞', 'ladybug', 'ladybugs']
];

/* How each letter's name should be spoken (text-to-speech reads a lone "A" as "uh"). */
window.ABC_NAMES = {
  A: 'Ay', B: 'Bee', C: 'See', D: 'Dee', E: 'Ee', F: 'Eff', G: 'Gee', H: 'Aitch', I: 'Eye',
  J: 'Jay', K: 'Kay', L: 'Ell', M: 'Em', N: 'En', O: 'Oh', P: 'Pee', Q: 'Cue', R: 'Are',
  S: 'Ess', T: 'Tee', U: 'You', V: 'Vee', W: 'Double you', X: 'Ex', Y: 'Why', Z: 'Zee'
};
