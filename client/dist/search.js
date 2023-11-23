document.addEventListener('DOMContentLoaded', () => {
  const targetElement = document.getElementById(
    'Menu-Sunnysideup-SiteWideSearch-Admin-SearchAdmin'
  )

  if (targetElement) {
    const securityID =
      window.ss && window.ss.config && window.ss.config['SecurityID']
    if (securityID) {
      const inputId = 'QuickSearchKeyword' + securityID
      const holder = document.createElement('a')
      // holder.setAttribute('href', '/admin/find/')
      const form = document.createElement('form')
      form.setAttribute('method', 'post') // or 'post', depending on your needs
      form.setAttribute('action', '/admin/find/EditForm') // Update with your form action

      const input = document.createElement('input')
      input.setAttribute('type', 'text')
      input.setAttribute('name', 'Keywords')
      input.setAttribute('placeholder', 'Search...')
      input.setAttribute('id', '')

      const hiddenInput = document.createElement('input')
      hiddenInput.setAttribute('type', 'hidden')
      hiddenInput.setAttribute('name', 'SecurityID')
      hiddenInput.setAttribute('value', securityID)
      hiddenInput.setAttribute('class', inputId)
      // hiddenInput.setAttribute('id', 'Form_EditForm_SecurityID')

      while (targetElement.firstChild) {
        targetElement.removeChild(targetElement.firstChild)
      }
      form.appendChild(input)
      form.appendChild(hiddenInput)
      form.classList.add('sunny-side-up-quick-search-form')
      holder.appendChild(form)
      targetElement.appendChild(holder)
      const newInput = document.getElementById(inputId)
      if (newInput) {
        newInput.addEventListener('keyup', function (event) {
          if (event.key === 'Enter') {
            console.log('Enter key pressed')
            event.preventDefault() // Prevents the default form submit on Enter press

            var keywords = this.value.trim()

            if (keywords === '') {
              window.location.href = '/admin/find/' // Redirects if the input is empty
            } else {
              this.form.submit() // Submits the form if the input has values
            }
          }
        })
      }
    }
  }
})
