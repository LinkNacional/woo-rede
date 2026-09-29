import React from 'react';
import Cards from 'react-credit-cards';
import 'react-credit-cards/es/styles-compiled.css';
const settingsRedeDebit = window.wc.wcSettings.getSetting('rede_debit_data', {})
const labelRedeDebit = window.wp.htmlEntities.decodeEntities(settingsRedeDebit.title)
// Obtendo o nonce da variável global
const nonceRedeDebit = settingsRedeDebit.nonceRedeDebit
const translationsRedeDebit = settingsRedeDebit.translations

const ContentRedeDebit = (props) => {
  const { eventRegistration, emitResponse } = props
  const { onPaymentSetup } = eventRegistration
  const wcComponents = window.wc.blocksComponents
  const [debitObject, setDebitObject] = window.wp.element.useState({
    rede_debit_number: '',
    rede_debit_installments: '1',
    rede_debit_expiry: '',
    rede_debit_cvc: '',
    rede_debit_holder_name: ''
  })
  const [focus, setFocus] = window.wp.element.useState('')

  const formatDebitCardNumber = value => {
    if (value?.length > 24) return debitObject.rede_debit_number
    // Remove caracteres não numéricos
    const cleanedValue = value?.replace(/\D/g, '')
    // Adiciona espaços a cada quatro dígitos
    const formattedValue = cleanedValue?.replace(/(.{4})/g, '$1 ')?.trim()
    return formattedValue
  }

  const onlyDigits = value => String(value == null ? '' : value).replace(/\D/g, '')

  // Validade padronizada: só dígitos, sempre MM/AA (sem espaços). Mês de um dígito
  // 2-9 vira 0X; mês > 12 é limitado a 12; ano com 4 dígitos é cortado para 2
  // ("25/2035" -> "25/35").
  const formatExpiryValue = value => {
    const digits = onlyDigits(value)
    let month = digits.slice(0, 2)
    let year = digits.slice(2)
    if (month.length === 1 && month >= '2' && month <= '9') {
      month = '0' + month
    } else if (month.length === 2 && parseInt(month, 10) > 12) {
      month = '12'
    }
    if (year.length > 2) year = year.slice(-2)
    return year.length ? month + '/' + year : month
  }

  const formatCvcValue = value => onlyDigits(value).slice(0, 4)

  const updateDebitObject = (key, value) => {
    switch (key) {
      case 'rede_debit_expiry':
        setDebitObject({
          ...debitObject,
          [key]: formatExpiryValue(value)
        })
        return
      case 'rede_debit_cvc':
        setDebitObject({
          ...debitObject,
          [key]: formatCvcValue(value)
        })
        return
      default:
        break
    }
    setDebitObject({
      ...debitObject,
      [key]: value
    })
  }

  window.wp.element.useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      // Verifica se todos os campos do debitObject estão preenchidos
      const allFieldsFilled = Object.values(debitObject).every((field) => field.trim() !== '')

      if (allFieldsFilled) {
        return {
          type: emitResponse.responseTypes.SUCCESS,
          meta: {
            paymentMethodData: {
              rede_debit_number: debitObject.rede_debit_number,
              rede_debit_installments: debitObject.rede_debit_installments,
              rede_debit_expiry: debitObject.rede_debit_expiry,
              rede_debit_cvc: debitObject.rede_debit_cvc,
              rede_debit_holder_name: debitObject.rede_debit_holder_name,
              rede_card_nonce: nonceRedeDebit
            }
          }
        }
      }
      return {
        type: emitResponse.responseTypes.ERROR,
        message: translationsRedeDebit.fieldsNotFilled
      }
    })

    // Cancela a inscrição quando este componente é desmontado.
    return () => {
      unsubscribe()
    }
  }, [
    debitObject, // Adiciona debitObject como dependência
    emitResponse.responseTypes.ERROR,
    emitResponse.responseTypes.SUCCESS,
    onPaymentSetup,
    translationsRedeDebit // Adicione translationsRedeDebit como dependência
  ])

  return (
    <>
      <Cards
        number={debitObject.rede_debit_number}
        name={debitObject.rede_debit_holder_name}
        expiry={(debitObject.rede_debit_expiry).replace(/\s+/g, '')}
        cvc={debitObject.rede_debit_cvc}
        placeholders={{
          name: 'NOME', 
          expiry: 'MM/ANO',
          cvc: 'CVC',
          number: '•••• •••• •••• ••••'
        }}
        locale={{ valid: 'VÁLIDO ATÉ' }}
        focused={focus}
      />
      <wcComponents.TextInput
        id="rede_debit_holder_name"
        label={translationsRedeDebit.nameOnCard}
        value={debitObject.rede_debit_holder_name}
        maxLength={30}
        onChange={(value) => {
          updateDebitObject('rede_debit_holder_name', value)
        }}
        onFocus={() => setFocus('name')}
      />

      <wcComponents.TextInput
        id="rede_debit_number"
        label={translationsRedeDebit.cardNumber}
        value={formatDebitCardNumber(debitObject.rede_debit_number)}
        inputMode="numeric"
        onChange={(value) => {
          updateDebitObject('rede_debit_number', formatDebitCardNumber(value))
        }}
        onFocus={() => setFocus('number')}
      />

      <wcComponents.TextInput
        id="rede_debit_expiry"
        label={translationsRedeDebit.cardExpiringDate}
        value={debitObject.rede_debit_expiry}
        inputMode="numeric"
        onChange={(value) => {
          updateDebitObject('rede_debit_expiry', value)
        }}
        onFocus={() => setFocus('expiry')}
      />

      <wcComponents.TextInput
        id="rede_debit_cvc"
        label={translationsRedeDebit.securityCode}
        value={debitObject.rede_debit_cvc}
        inputMode="numeric"
        onChange={(value) => {
          updateDebitObject('rede_debit_cvc', value)
        }}
        onFocus={() => setFocus('cvc')}
      />
    </>
  )
}

const BlockGatewayRedeDebit = {
  name: 'rede_debit',
  label: labelRedeDebit,
  content: window.wp.element.createElement(ContentRedeDebit),
  edit: window.wp.element.createElement(ContentRedeDebit),
  canMakePayment: () => true,
  ariaLabel: labelRedeDebit,
  supports: {
    features: settingsRedeDebit.supports
  }
}

window.wc.wcBlocksRegistry.registerPaymentMethod(BlockGatewayRedeDebit)
