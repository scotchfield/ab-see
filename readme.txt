=== A/B See ===
Contributors: sgrant
Donate link: http://scootah.com/
Tags: ab-test, a/b testing, a/b test, split test, split testing, testing, website optimization
Requires at least: 4.0
Tested up to: 4.2.2
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Straightforward shortcodes for A/B testing with WordPress.

== Description ==

Want to start a split-test right away? Choose a unique test id, provide a piece of code for the first group and a piece of code for the second, and define an id to indicate a conversion.

For example, let's define a split test called <strong>clothes</strong>. In the first group, we'll say "Your clothes are red." In the second group, we'll say "Your clothes are black." When we convert, we'll use the id <strong>clothes_complete</strong>.

Then, we'll do this on our test page:

&lt;a href="convert.php"&gt;<strong>[ab-see id=clothes]</strong>&lt;/a&gt;

And on the page we want to get to:

<strong>[ab-convert id=clothes_complete]</strong>

That's it!

Inspired by the practice of copying the smartest people you know, AB-See is based on Patrick McKenzie’s A/Bingo (http://www.bingocardcreator.com/abingo) and Ben Kamens' GAE/Bingo (http://bjk5.com/post/10171483254/a-bingo-split-testing-now-on-app-engine-built-for).

Built for our A/B testing needs at Scent Trunk (https://scenttrunk.com).

== Installation ==

Place all the files in a directory inside of wp-content/plugins (for example, ab-see), and activate the plugin.

== Frequently Asked Questions ==

= Why is my test not working? =

Tests are not enabled by default after being created! Make sure your test is enabled,
and that you have the correct test id in the ab-see shortcode.

= How can I test that both groups are working? =

You can amend the group_override parameter to force tests to use a particular group. For example,
http://scenttrunk.com?group_override=1 will force tests from the first group to show, and
http://scenttrunk.com?group_override=2 will force tests from the second group.

== Screenshots ==

1. The A/B See admin panel. Lists tests by active/inactive, check out impressions and conversions, and edit tests if necessary.
2. An example of a split test with different text.

== Changelog ==

= 1.0 =
* First release!

== Upgrade Notice ==

= 1.0 =
First public release.
