[![Latest Stable Version](https://poser.pugx.org/vladahejda/livetranslator/v/stable.png)](https://packagist.org/packages/vladahejda/livetranslator)
[![Total Downloads](https://poser.pugx.org/vladahejda/livetranslator/downloads.png)](https://packagist.org/packages/vladahejda/livetranslator)
[![Montly Downloads](https://poser.pugx.org/vladahejda/livetranslator/d/monthly.png)](https://packagist.org/packages/vladahejda/livetranslator)
[![Daily Downloads](https://poser.pugx.org/vladahejda/livetranslator/d/daily.png)](https://packagist.org/packages/vladahejda/livetranslator)

[![Build Status](https://secure.travis-ci.org/VladaHejda/LiveTranslator.png?branch=master)](https://travis-ci.org/VladaHejda/LiveTranslator)


[DEMO](http://livetranslator.hejdav.cz/)

LiveTranslator
===

LiveTranslator is tool for [Nette Framework](http://nette.org/en/).

*LiveTranslator is forked from [NetteTranslator](https://github.com/straiki/NetteTranslator).*


Installation
---

- Download from Github: <https://github.com/VladaHejda/LiveTranslator>
- or better use [Composer](http://getcomposer.org/doc/00-intro.md#declaring-dependencies):

```json
{
	"require": {
		"vladahejda/livetranslator": "~1.0"
	},
	"minimum-stability": "RC"
}
```

Then load classes via autoloader ([composer autoloading](http://getcomposer.org/doc/01-basic-usage.md#autoloading)
or Nette RobotLoader).


Usage
---

To launch the translator follow these steps:


### 1. prepare storage

- **I have no database**

You can store translations into plaintext file. Just add following service into your config
and choose persistent and write-accessible (existing) directory:
```
services:
	translatorStorage: LiveTranslator\Storage\File(%appDir%/../data/localization)
```

- **I want to save translations elsewhere**

Look at the interface `LiveTranslator\ITranslatorStorage` and implement it to write your own storage.

Then add storage into your configuration file as a service.

### 3. set up your BasePresenter

Inject LiveTranslator, set current language and give translator to template and forms:
```php
class BasePresenter extends \Nette\Application\UI\Presenter
{
	/** @var string @persistent */
	public $lang = 'en';

	/** @var \LiveTranslator\Translator @inject */
	public $translator;

	// since Nette 2.1 you can omit this method
	public function injectTranslator(\LiveTranslator\Translator $translator)
	{
		$this->translator = $translator;
	}

	public function startup()
	{
		parent::startup();
		$this->translator->setCurrentLang($this->lang);
	}
	
	protected function createTemplate($class = NULL)
	{
		$template = parent::createTemplate($class);
		$template->setTranslator($this->translator);
		return $template;
	}

	// to have translated even forms add this method too
	protected function createComponent($name)
	{
		$component = parent::createComponent($name);
		if ($component instanceof \Nette\Forms\Form) {
			$component->setTranslator($this->translator);
		}
		return $component;
	}
}
```

Advanced
---

If you want to use your translator fully, there is some more stuff you would do.


### Say translator which languages you use

Call function `$translator->setAvailableLanguages()` and give the array of languages that your web is available in.
Then set the name of presenter language persistent parameter (`$translator->setPresenterLanguageParam()`).

Better way of this is setting it in config:
```
	translator:
		class: LiveTranslator\Translator(en)
		setup:
			- setAvailableLanguages([en, de, fr])
			- setPresenterLanguageParam(lang)
```

### Use skills of `sprintf`

If you give more arguments to translate method, it will be handed to php function
[sprintf](http://php.net/manual/en/function.sprintf.php).

That means that `$translator->translate('Call me %s.', 'Johan')` results in "Call me Johan", whereas
"Johan" will not be translated.

It can be used in latte too.


### Translate plurals (1 apple → 2 apples)

You can say what plural-form each language uses via `setAvailableLanguages`, this way:
```
	setup:
		- setAvailableLanguages([
			en: "nplurals=2; plural=(n==1) ? 0 : 1;",
			cz: "nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));",
		])
```
(to understand this see [plural forms](https://github.com/translate/l10n-guide/blob/master/docs/l10n/pluralforms.rst#plural-forms))

More you need to do is to give plural variants
of the default language to the translator, in array. And the number. Example:
`$translator->translate( array( 'There is %d apple', 'There are %d apples' ), 3 )`
or in latte: `{_ ['There is %d apple', 'There are %d apples'], 3}`.


### Using namespaces

When there is huge amount of texts at your web, it would be good to sort them somehow. Just give the namespace
to the translator dependent for example on [module](http://doc.nette.org/en/presenters#toc-modules).
`$translator->setNamespace('products')`

And now, enjoy.


Authors
---

*(alphabetic order)*

- Josef Kufner (jk@frozen-doe.net)
- Miroslav Paulík (https://github.com/castamir)
- Roman Sklenář (http://romansklenar.cz)
- Miroslav Smetana
- Jan Smitka
- Patrik Votoček (patrik@votocek.cz)
- Tomáš Votruba (tomas.vot@gmail.com)
- Václav Vrbka (gmvasek@php-info.cz)
- Vladislav Hejda


Under *New BSD License*
