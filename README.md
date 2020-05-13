# Regressions report tool

This tool is used to track all issues created in the last month (4 weeks) for the 
[PrestaShop](https://github.com/PrestaShop/PrestaShop) project. It will gather all its data from the GitHub v3 API.

## How it works

You give the script a `START_DATE` and an `END_DATE` through environment variables.

It will gather all issues created on this timeframe and :
- count the open and closed issues
- count regressions (including those found by automated tests)
- count duplicates
- create a table showing all duplicates and their original issue (along with the original issue status and priority)

## How to use

Create a file named `token.txt` at the root of the project and put your GitHub token in it.

You can use these environment variables:
- `START_DATE` : specify the start date of the timeframe (**mandatory**).
- `END_DATE`   : specify the end date of the timeframe. If not specified, will be calculated by adding 4 weeks 
to `START_DATE`. 

Launch the script by using this command :

```php
START_DATE=2020-05-01 END_DATE=2020-05-29 php generate.php
```

The final report is created in the `/reports/` folder. It uses the `template.md` file. You can modify it. 
Here are the main variables:

| Variable | Description |
| -------- | ----------- |
| %start-date% | Start date of the timeframe. Format `YYYY-M-DD` |
| %end-date% | End date of the timeframe. Format `YYYY-M-DD` |
| %issues-created% | Number of issues created on the period |
| %issues-open% | Number of open issues |
| %issues-closed% | Number of closed issues |
| %issues-duplicates% | Number of duplicates |
| %issues-duplicates-percentage% | Number of duplicates as a percentage (from all issues) |
| %issues-regressions% | Number of regressions |
| %issues-regressions-percentage% | Number of regressions as a percentage (from all issues) |
| %issues-detected-by-te% | Number of issues detected by automated tests |
| %issues-detected-by-te-percentage% | Number of issues detected by automated tests as a percentage (from all issues) |
| %duplicate-table% | Table of duplicate (fields: #, title, creation date, targeting BO or FO, Original issue, Origin status, Origin priority) |
|%creation-date% | Report creation datetime |
