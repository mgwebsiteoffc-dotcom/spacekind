"""Sign in to the local QA installation using its real password form."""
import os


async def password_login(page, role):
    if role == 'admin':
        email = os.environ['SPACEKIND_QA_ADMIN_EMAIL']
        password = os.environ['SPACEKIND_QA_ADMIN_PASSWORD']
    else:
        email = 'owner@aangan.example'
        password = 'Spacekind@2026'  # Fictional studio-only local fixture.
    form = page.locator('form[action="/login"]')
    await form.locator('[name="email"]').fill(email)
    await form.locator('[name="password"]').fill(password)
    await form.locator('button').click()
