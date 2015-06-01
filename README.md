# ab-see
Straightforward shortcodes for A/B testing with WordPress.

## Overview
Want to start a split-test right away? Choose a unique test id, provide a piece of code for the first group and a piece of code for the second, and define an id to indicate a conversion.

For example, let's define a split test called *clothes*. In the first group, we'll say "Your clothes are red." In the second group, we'll say "Your clothes are black." When we convert, we'll use the id *clothes_complete*.

Then, we'll do this:

**On your test page:**

&lt;a href="convert.php"&gt;**[ab-see id=clothes]**&lt;/a&gt;

**Somewhere in convert.php:**

**[ab-convert id=clothes_complete]**

And that's it.

## Background

Inspired by the practice of copying the smartest people you know, AB-See is based on [Patrick McKenzie’s A/Bingo](http://www.bingocardcreator.com/abingo) and [Ben Kamens' GAE/Bingo](http://bjk5.com/post/10171483254/a-bingo-split-testing-now-on-app-engine-built-for).

Built for our A/B testing needs at [Scent Trunk](https://scenttrunk.com).
