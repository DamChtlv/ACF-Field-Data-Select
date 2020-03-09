# ACF Field: Field Data Select

## Description

___

**What does this field?**

- It allows you to **select / return data** from **another field** *(radio, checkbox, select, repeater or flexible content)* either from **default value** or from **another source** *(post/page/user/term/option page)*.
- This way, you can use it to make a optimized **reusable* / *component-like** system.

___

## How it works?

In the admin, it will **render** you a **select field** so you can choose a **specific value**:
- If you target a **checkbox / radio / select**, it will allow you to target a **specific choice**.
- If you target a **repeater field**, it will allow you to target a **specific row**.
- If you target a **flexible content field**, it will allow you to target a **specific layout**.

___

## Example

You have a **option page** containing a **group field** with **tons of fields** inside it that is acting as a **configuration** for something.
You **want to use** all this configuration **multiple times** for some reason, this field will allow you to target that with ACF UI and get the data like you would do it with a **get_field()**.
